#!/usr/bin/env python3
"""只读复算 8013 和现用记录源，生成第二轮记录模板逐字段候选 v0.2。"""

from __future__ import annotations

import hashlib
import json
import subprocess
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


HERE = Path(__file__).resolve().parent
PACKAGE_DIR = HERE.parent
CANDIDATE_DIR = PACKAGE_DIR / "候选覆盖层"
CONTAINER = "lims-zhj-rehearsal-r2-main-20260719-db-1"
DB = "jewelry_qms"


def find_project_root() -> Path:
    for path in [PACKAGE_DIR, *PACKAGE_DIR.parents]:
        if (path / ".git").exists():
            return path
    raise RuntimeError("找不到项目根目录")


ROOT = find_project_root()
RECORD_ROOT = ROOT / "现用文件/记录表格/记录表格2017"


def sha(path: Path, algorithm: str = "sha256") -> str:
    digest = hashlib.new(algorithm)
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def mysql(sql: str) -> list[list[str]]:
    result = subprocess.run(
        [
            "docker",
            "exec",
            CONTAINER,
            "mysql",
            "--default-character-set=utf8mb4",
            "-uroot",
            "-N",
            "-B",
            "-e",
            f"USE {DB}; {sql}",
        ],
        check=True,
        capture_output=True,
        text=True,
    )
    return [line.split("\t") for line in result.stdout.splitlines() if line.strip()]


def extract_text_hash(path: Path) -> str:
    result = subprocess.run(
        ["textutil", "-convert", "txt", "-stdout", str(path)],
        check=True,
        capture_output=True,
    )
    return hashlib.sha256(result.stdout).hexdigest()


def find_source(name: str) -> Path:
    matches = list(RECORD_ROOT.rglob(name))
    if len(matches) != 1:
        raise RuntimeError(f"源文件无法唯一定位：{name} -> {matches}")
    return matches[0]


CORRECTION = "保留原值、新值、更正人、更正时间和理由；锁定后不得覆盖原值，按CX-19更正规则处理"
RETENTION_STATUS = "CX-19 4.5.3.3提供一般规则；本表具体期限须与BG-19-03/04及更长期外部要求复核，当前不编造期限"
SOURCE_LEVELS = {
    "E1": "direct_source",
    "E2": "cross_source",
    "E3": "sim_extension",
    "E0": "evidence_insufficient",
}


def field(
    key: str,
    name: str,
    locator: str,
    meaning: str,
    role: str,
    trigger: str,
    required: str,
    mapping: str,
    level: str,
    clause: str,
    associations: list[str],
    action: str = "include_in_candidate",
) -> dict[str, Any]:
    return {
        "field_key": key,
        "field_name": name,
        "source_locator": locator,
        "procedure_clause_or_frozen_fact": clause,
        "business_meaning": meaning,
        "responsible_role": role,
        "trigger": trigger,
        "required_rule": required,
        "correction_rule": CORRECTION,
        "associations": associations,
        "retention_basis_status": RETENTION_STATUS,
        "lims_mapping": mapping,
        "source_level": SOURCE_LEVELS[level],
        "candidate_action": action,
        "proposed_value": "",
    }


def F(
    key: str,
    name: str,
    locator: str,
    meaning: str,
    role: str,
    trigger: str,
    required: str,
    mapping: str,
    level: str = "E1",
    clause: str = "现用原表字段",
    assoc: list[str] | None = None,
    action: str = "include_in_candidate",
) -> dict[str, Any]:
    return field(
        key,
        name,
        locator,
        meaning,
        role,
        trigger,
        required,
        mapping,
        level,
        clause,
        assoc or [],
        action,
    )


RECORD_SPECS: list[dict[str, Any]] = [
    {
        "doc_number": "XZTC/BG-09-01",
        "title": "合同评审记录表",
        "source_file_name": "09-01合同评审记录表.doc",
        "procedure": "XZTC/CX-09-2022",
        "fields": [
            F("client_name", "委托单位", "表头“委托单位”", "识别委托方", "综合办公室/样品管理员", "建立合同评审", "required", "client_name", assoc=["委托/合同"]),
            F("project_name", "项目名称", "表头“项目名称”", "识别受评审项目", "综合办公室/样品管理员", "建立合同评审", "required", "project_name", assoc=["委托/合同"]),
            F("contract_number", "合同编号", "表头“合同编号”", "关联书面合同", "综合办公室", "存在书面合同或协议", "conditional", "contract_number", "E2", "CX-09 4.2-4.4", ["委托/合同"]),
            F("standards_and_parameters", "参数/标准及版本", "表头“参数”", "识别项目、参数和方法版本", "检测室提供/组织人录入", "确定检测项目和方法", "required", "standards_and_parameters[]", clause="CX-09 4.3,4.5.1", assoc=["方法清单", "报告"]),
            F("scope_capable", "检测项目是否在能力范围", "评审内容1", "判断技术能力范围", "技术负责人/简评样品管理员", "每次全面评审或首次简评", "required", "scope_capable", clause="CX-09 4.5.2", assoc=["能力范围"]),
            F("oral_requirement_confirmed", "口头约定后是否与客户确认", "评审内容2", "确认口头要求", "综合办公室", "存在口头要求", "conditional", "oral_requirement_confirmed", clause="CX-09 4.1", assoc=["客户沟通"]),
            F("written_contract_signed", "是否签订文字合同/协议", "评审内容3", "确认合同形式", "综合办公室", "确定合同形式", "required", "written_contract_signed", clause="CX-09 4.2-4.4", assoc=["合同"]),
            F("equipment_capable", "仪器设备是否满足", "评审内容4", "判断设备资源满足性", "技术负责人/检测室", "资源评审", "required", "equipment_capable", clause="CX-09 4.5.2", assoc=["设备"]),
            F("personnel_capable", "人员数量与技能是否满足", "评审内容5", "判断人员能力满足性", "技术负责人/检测室", "资源评审", "required", "personnel_capable", clause="CX-09 4.5.2", assoc=["人员授权"]),
            F("delivery_capable", "能否按时提供报告", "评审内容6", "判断交付能力", "综合办公室会同检测室", "交付评审", "required", "delivery_capable", clause="CX-09 4.5", assoc=["报告"]),
            F("method_capable", "方法能否满足项目", "评审内容7", "判断方法适用性", "技术负责人/检测室", "方法选择", "required", "method_capable", clause="CX-09 4.1,4.3", assoc=["方法"]),
            F("legal_compliance", "是否符合法律法规", "评审内容8", "判断合规边界", "技术负责人", "合规评审", "required", "legal_compliance", clause="CX-09 4.5.7", assoc=["外部依据"]),
            F("subcontract_required", "是否需分包", "评审内容9", "记录分包适用性；本轮固定否", "技术负责人", "每次全面评审", "required_fixed_no_for_sim", "subcontract_required", "E2", "CX-09 4.3；冻结事实不分包", ["NEG-03"]),
            F("other_review_items", "其他评审内容", "评审内容10", "补充特殊要求", "评审组织人", "存在其他要求", "optional", "other_review_items"),
            F("conclusion_all_items", "能否以适当方法完成全部项目", "评审结论1", "形成方法能力结论", "技术负责人", "评审收口", "required", "conclusion_all_items", assoc=["评审结论"]),
            F("conclusion_resources", "人员/技术/设备/环境能否满足", "评审结论2", "形成资源结论", "技术负责人", "评审收口", "required", "conclusion_resources", assoc=["评审结论"]),
            F("conclusion_delivery", "合同期能否按时提供报告", "评审结论3", "形成交付结论", "技术负责人", "评审收口", "required", "conclusion_delivery", assoc=["评审结论"]),
            F("conclusion_other", "其他结论", "评审结论4", "补充结论", "技术负责人", "存在补充结论", "optional", "conclusion_other"),
            F("review_signatures", "评审人员会签", "页尾会签区", "证明评审参与和批准", "全体评审人/批准人", "结论形成后", "required", "review_signatures[]", clause="CX-09 4.6-4.7", assoc=["权限"]),
            F("review_date", "评审日期", "原表无独立栏；签核时点要求", "记录评审完成时间", "评审组织人", "会签", "pending_confirmation", "review_date", "E3", "GB/T 27025-2019 7.1；冻结运行时间线", ["审计轨迹"]),
            F("site_id", "适用场所", "原表无；第二轮冻结扩展", "限定实施场所", "评审组织人/技术负责人", "两场所能力判定", "conditional", "site_id", "E3", "冻结画像第5节", ["场所", "授权"]),
            F("cma_scope_status", "CMA范围核验状态", "原表无；第二轮冻结扩展", "逐项目判定CMA状态", "质量负责人/授权复核岗位", "涉及CMA标志", "conditional", "cma_scope_status", "E3", "一单一库冻结", ["报告标志"]),
            F("cnas_status", "CNAS状态", "原表无；第二轮冻结扩展", "固定初次申请筹备且未认可", "质量负责人", "涉及CNAS声明", "required", "cnas_status", "E3", "冻结画像第5节", ["报告标志"]),
            F("allowed_report_marks", "允许的报告标志", "原表无；第二轮冻结扩展", "控制报告标志", "质量负责人/报告复核岗位", "确定报告属性", "required", "allowed_report_marks[]", "E3", "CMA/CNAS冻结考卷", ["报告"]),
            F("limitations_and_customer_notice", "限制/客户告知", "原表无；第二轮冻结扩展", "记录能力或标志限制及客户沟通", "综合办公室", "存在限制", "conditional", "limitations_and_customer_notice", "E3", "CX-09 4.1；一单一库冻结", ["客户沟通"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-28-01",
        "title": "样品台账",
        "source_file_name": "28-01样品台帐.doc",
        "procedure": "XZTC/CX-28-2022",
        "fields": [
            F("sequence_no", "序号", "表头“序号”", "标识台账行", "系统/样品管理员", "新增样品行", "required", "sequence_no", "E2", "原表字段；系统顺序号", ["样品"]),
            F("entrustment_number", "委托单号", "表头“委托单号”", "关联委托", "样品管理员", "接收样品", "required", "entrustment_number", clause="CX-28 4.1.2", assoc=["委托"]),
            F("customer_name", "客户名称", "表头“客户名称”", "识别送样客户", "样品管理员", "接收样品", "required", "customer_name", assoc=["客户"]),
            F("received_at", "来样日期", "表头“来样日期”", "记录接收时间", "样品管理员", "接收样品", "required", "received_at", clause="CX-28 4.1", assoc=["样品接收"]),
            F("sample_name", "样品名称", "表头“样品名称”", "识别样品", "样品管理员", "接收样品", "required", "sample_name", assoc=["样品"]),
            F("quantity", "数量", "表头“数量”", "记录接收数量", "样品管理员", "初验清点", "required", "quantity", clause="CX-28 4.1.1", assoc=["样品"]),
            F("sample_number", "样品编号", "表头“样品编号”", "形成唯一标识", "样品管理员", "统一编号", "required", "sample_number", clause="CX-28 4.2.1", assoc=["样品唯一标识"]),
            F("test_items_and_requirements", "测试项目及要求", "表头“测试项目及要求”", "关联检测要求", "样品管理员/检测室", "接收和委托确认", "required", "test_items_and_requirements", clause="CX-28 4.1.1-4.1.2", assoc=["方法", "委托"]),
            F("sample_administrator", "样品管理员", "表头“样品管理员”", "记录接收责任人", "样品管理员", "接收登记", "required", "sample_administrator", clause="CX-28 3.2", assoc=["人员"]),
            F("received_by", "样品领取人", "表头“样品领取人”", "记录交接领取人", "实际领取人", "进入检测室", "conditional", "received_by", clause="CX-28 4.3.1", assoc=["流转"]),
            F("returned_by", "还样人", "表头“还样人”", "记录退还责任人", "实际还样人", "退回或交回", "conditional", "returned_by", "E2", "原表字段", ["流转"]),
            F("checkout_at", "领取时间", "表头“领取时间”", "记录领取时点", "样品管理员/领取人", "领取", "conditional", "checkout_at", assoc=["流转"]),
            F("remarks", "备注", "表头“备注”", "记录异常或约定", "样品管理员", "异常或补充", "conditional_on_exception", "remarks", "E2", "原表字段", ["异常"]),
            F("custody_events", "样品状态/流转节点", "原表无独立列；程序要求状态控制", "保存逐节点流转事件", "各节点责任人", "状态变化", "conditional", "custody_events[]", "E3", "CX-28 4.3", ["审计轨迹"]),
            F("sampling_responsibility", "不抽样/客户送样标记", "原表无；第二轮冻结扩展", "明确样品由客户送检", "样品管理员", "接收", "required_fixed_customer_submitted", "sampling_responsibility", "E3", "冻结事实不抽样", ["NEG-01"]),
            F("retention_decision", "不留样/例外留存决定", "原表无；第二轮冻结扩展", "记录不留样及合法例外评审", "样品管理员/技术负责人复核例外", "完检处置前", "required", "retention_decision", "E3", "冻结事实不留样", ["NEG-02"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-22-02",
        "title": "标准方法确认记录",
        "source_file_name": "22-02标准方法确认记录.doc",
        "procedure": "XZTC/CX-22-2022",
        "fields": [
            F("method_name", "标准方法名称/代号（含年号）", "表头", "识别方法和版本", "确认人", "首次使用/换版/重新确认", "required", "method_name", assoc=["方法"]),
            F("confirmation_group_or_person", "确认组别/确认人", "表头", "识别确认主体", "确认组织人", "建立记录", "required", "confirmation_group_or_person", "E2", "原表字段", ["人员"]),
            F("confirm_personnel", "人员确认", "确认内容“人员”", "确认人员条件", "确认人", "资源确认", "required", "confirm_personnel", assoc=["人员"]),
            F("confirm_equipment", "设备确认", "确认内容“设备”", "确认设备条件", "确认人", "资源确认", "required", "confirm_equipment", assoc=["设备"]),
            F("confirm_reagent_standard", "试剂标准确认", "确认内容“试剂标准”", "确认试剂/标准品条件", "确认人", "资源确认", "conditional", "confirm_reagent_standard", assoc=["标准物质"]),
            F("confirm_environment", "环境条件确认", "确认内容“环境条件”", "确认环境条件", "确认人", "资源确认", "required", "confirm_environment", assoc=["环境"]),
            F("understanding_of_principle", "对标准原理的理解", "人员项", "评价方法理解", "确认对象/确认人", "人员确认", "required", "understanding_of_principle"),
            F("operation_experience", "是否进行过操作", "人员项", "评价操作经历", "确认对象/确认人", "人员确认", "required", "operation_experience"),
            F("familiarity_with_operation", "操作过程熟悉程度", "人员项", "评价操作熟悉程度", "确认对象/确认人", "人员确认", "required", "familiarity_with_operation"),
            F("equipment_name", "主要设备名称", "设备项", "识别实施设备", "确认人", "设备确认", "conditional", "equipment_name", assoc=["设备"]),
            F("equipment_satisfaction", "设备满足性", "设备项", "形成设备结论", "确认人", "设备确认", "required", "equipment_satisfaction", assoc=["设备"]),
            F("reagent_availability", "是否有要求的试剂", "试剂标准项", "确认试剂可用性", "确认人", "方法要求试剂", "conditional", "reagent_availability"),
            F("standard_availability", "是否有要求的标准品", "试剂标准项", "确认标准品可用性", "确认人", "方法要求标准品", "conditional", "standard_availability"),
            F("reagent_standard_satisfaction", "试剂标准满足性", "试剂标准项", "形成试剂标准结论", "确认人", "适用时", "conditional", "reagent_standard_satisfaction"),
            F("env_satisfaction", "环境条件满足性", "环境项", "形成环境结论", "确认人", "环境确认", "required", "env_satisfaction", assoc=["环境"]),
            F("env_special_requirement", "是否有特殊环境要求", "环境项", "识别特殊环境要求", "确认人", "环境确认", "required", "env_special_requirement", assoc=["环境"]),
            F("env_special_requirement_desc", "特殊环境要求描述", "环境项", "描述特殊环境要求", "确认人", "选择有特殊要求", "conditional", "env_special_requirement_desc", assoc=["环境"]),
            F("remarks", "备注", "备注区", "补充说明", "确认人", "有补充", "optional", "remarks"),
            F("confirmation_conclusion", "确认结论", "结论区", "形成方法确认结论", "确认人", "确认收口", "required", "confirmation_conclusion", assoc=["方法"]),
            F("confirmation_opinion", "确认意见", "结论区", "说明结论依据或限制", "确认人", "结论形成", "conditional", "confirmation_opinion", assoc=["方法"]),
            F("confirmer_signature", "确认人签名", "签核区", "确认责任签核", "确认人", "完成确认", "required", "confirmer_signature", assoc=["权限"]),
            F("confirmer_date", "确认人日期", "签核区", "记录确认时点", "确认人", "完成确认", "required", "confirmer_date"),
            F("reviewer_signature", "复核者签名", "签核区", "复核责任签核", "复核者", "复核完成", "required", "reviewer_signature", assoc=["权限"]),
            F("reviewer_date", "复核者日期", "签核区", "记录复核时点", "复核者", "复核完成", "required", "reviewer_date"),
            F("tech_opinion", "各专业领域技术负责人意见", "技术负责人意见栏", "记录技术意见", "专业领域技术负责人", "技术确认", "conditional", "tech_opinion", clause="原表字段；CX-22 4.5", assoc=["技术负责人"]),
            F("tech_signature", "各专业领域技术负责人签名", "意见栏下签核", "技术责任签核", "专业领域技术负责人", "最终确认", "required", "tech_signature", assoc=["权限"]),
            F("tech_date", "各专业领域技术负责人日期", "意见栏下签核", "记录技术确认时点", "专业领域技术负责人", "最终确认", "required", "tech_date"),
            F("site_id", "适用场所", "原表无；第二轮冻结扩展", "限定方法实施场所", "技术负责人", "两场所能力不同", "conditional", "site_id", "E3", "冻结画像第5节", ["场所"]),
            F("authorization_scope", "授权范围", "原表无；第二轮冻结扩展", "关联人员方法活动授权", "技术负责人", "人员授权有差异", "conditional", "authorization_scope", "E3", "冻结画像第5节", ["人员授权"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-29-02",
        "title": "报告抽查情况记录表",
        "source_file_name": "29-02报告抽查情况记录表.doc",
        "procedure": "XZTC/CX-29-2022",
        "fields": [
            F("test_item", "检测项目", "表头", "识别抽查项目", "监督员", "建立抽查批次", "required", "test_item"),
            F("entrustment_number", "委托编号", "表头", "关联被抽查业务", "监督员", "抽取报告", "required", "entrustment_number", assoc=["委托"]),
            F("sampled_report_count", "抽查数量", "表头", "记录抽查样本数", "监督员", "抽查统计", "required", "sampled_report_count"),
            F("defect_count", "缺陷数量", "表头", "记录缺陷数", "监督员", "抽查统计", "required", "defect_count"),
            F("method_followed", "是否按标准/方法完成", "抽查内容1", "检查方法执行", "监督员", "逐批检查", "required", "checks.method_followed", clause="CX-29 4.2,4.7"),
            F("equipment_matched", "仪器设备使用是否匹配", "抽查内容2", "检查设备匹配", "监督员", "逐批检查", "required", "checks.equipment_matched", assoc=["设备"]),
            F("environment_compliant", "环境条件是否符合", "抽查内容3", "检查环境符合", "监督员", "逐批检查", "required", "checks.environment_compliant", assoc=["环境"]),
            F("data_reasonable", "数据是否合理", "抽查内容4", "检查数据合理性", "监督员", "逐批检查", "required", "checks.data_reasonable"),
            F("conclusion_correct", "检测结论是否正确", "抽查内容5", "检查结论正确性", "监督员", "逐批检查", "required", "checks.conclusion_correct"),
            F("other_findings", "其他检查", "抽查内容6", "记录其他发现", "监督员", "有其他发现", "optional", "other_findings"),
            F("supervisor_signature", "监督员签名", "签核区", "抽查责任签核", "监督员", "完成抽查", "required", "supervisor_signature", clause="CX-29 4.7.3", assoc=["权限"]),
            F("inspection_date", "抽查日期", "签核区", "记录抽查时点", "监督员", "完成抽查", "required", "inspection_date"),
            F("technical_opinion", "技术负责人意见", "签核区", "记录处置决定", "技术负责人", "抽查结果处置", "required", "technical_opinion", clause="CX-29 4.7.4", assoc=["CAPA"]),
            F("technical_signature_date", "技术负责人签名/日期", "签核区", "处置责任签核", "技术负责人", "形成处置决定", "required", "technical_signature_date", assoc=["权限"]),
            F("report_trace", "报告编号/标准/CMA/CNAS标志", "原表无；第二轮冻结扩展", "关联具体报告并反测标志", "报告复核岗位/监督员", "抽到具体报告", "conditional", "report_trace", "E3", "一单一库及CNAS冻结", ["报告", "CMA/CNAS"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-02-01",
        "title": "检测环境监控记录表",
        "source_file_name": "02-01《检测环境监控记录表》.doc",
        "procedure": "XZTC/CX-02-2022",
        "fields": [
            F("record_month", "记录月份", "页首年/月", "标识月度记录", "检测人员", "新建月度记录", "required", "record_month"),
            F("site_area", "监控区域", "页首", "识别场所区域", "检测人员", "新建月度记录", "required", "site_area", assoc=["场所"]),
            F("temperature_requirement", "温度要求", "页首", "引用适用温度要求", "技术负责人设定/检测人员引用", "方法或场所要求建立/变更", "required", "temperature_requirement", clause="CX-02 4.2.3.4", assoc=["方法/场所要求"]),
            F("humidity_requirement", "湿度要求", "页首", "引用适用湿度要求", "技术负责人设定/检测人员引用", "方法或场所要求建立/变更", "required", "humidity_requirement", clause="CX-02 4.2.3.4", assoc=["方法/场所要求"]),
            F("observed_at", "监测日期/时间", "每日明细行", "记录观测时点", "检测人员", "每次监测", "required", "readings[].observed_at", "E2", "原表日行；时间粒度待核", ["审计轨迹"]),
            F("temperature_c", "温度（℃）", "明细列", "记录温度观测值", "检测人员", "每次监测", "required", "readings[].temperature_c"),
            F("humidity_percent", "湿度（%）", "明细列", "记录湿度观测值", "检测人员", "每次监测", "required", "readings[].humidity_percent"),
            F("recorded_by", "记录人", "明细列", "记录观测责任人", "检测人员", "每次监测", "required", "readings[].recorded_by", clause="CX-02 3.3.1"),
            F("remarks", "备注", "明细列", "记录偏离或调整", "检测人员", "偏离/异常", "conditional", "readings[].remarks"),
            F("monitoring_device_code", "温/湿度计编号", "页尾", "关联监测设备", "检测人员/设备管理员", "建立记录或设备变化", "required", "monitoring_device_code", assoc=["设备"]),
            F("deviation_action_id", "偏离处置/停止工作关联", "原表无专栏", "关联超限处置", "检测人员/技术负责人", "超限", "conditional", "deviation_action_id", "E3", "CX-02 4.2.3.6-4.2.3.7", ["不符合工作"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-30-01",
        "title": "年度内部质量监控计划表",
        "source_file_name": "30-01年度内部质量监控计划表.doc",
        "procedure": "XZTC/CX-30-2022",
        "fields": [
            F("sequence_no", "序号", "表头", "标识计划行", "系统/技术负责人", "新增计划行", "required", "plan_items[].sequence_no", "E2"),
            F("monitoring_item", "计划监控项目", "表头", "识别监控对象", "技术负责人", "年度策划", "required", "plan_items[].monitoring_item", clause="CX-30 4.2.1.1"),
            F("monitoring_method", "采用的监控方法", "表头", "记录监控方式", "技术负责人", "年度策划", "required", "plan_items[].monitoring_method", clause="CX-30 4.1.1"),
            F("planned_time", "计划监控时间", "表头", "安排实施时间", "技术负责人", "年度策划", "required", "plan_items[].planned_time", clause="CX-30 4.2.1.2"),
            F("evaluation_criteria", "评价准则", "表头", "记录误差/统计评价准则", "技术负责人", "年度策划", "required", "plan_items[].evaluation_criteria", clause="CX-30 4.2.1.5"),
            F("responsible_person", "监控实施负责人", "表头", "分配责任", "技术负责人指定", "年度策划", "required", "plan_items[].responsible_person", clause="CX-30 4.2.1.6"),
            F("remarks", "备注", "表头", "补充限制或状态", "技术负责人", "有补充", "optional", "plan_items[].remarks"),
            F("prepared_by_date", "编制人/日期", "原表无独立签核栏", "记录编制责任和时点", "技术负责人", "计划完成", "pending_confirmation", "prepared_by_date", "E3", "CX-30 3.1-3.3", ["权限"]),
            F("approved_by_date", "批准人/日期", "原表无独立签核栏", "记录批准责任和时点", "实验室主任", "计划实施前", "required", "approved_by_date", "E3", "CX-30 3.1.1,4.2.7", ["权限"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-30-03",
        "title": "能力验证计划表",
        "source_file_name": "30-03能力验证计划表.doc",
        "procedure": "XZTC/CX-30-2022",
        "fields": [
            F("laboratory_name", "实验室名称", "页首", "识别实验室", "技术负责人", "建立计划", "required", "laboratory_name"),
            F("sequence_no", "序号", "表头", "标识计划行", "系统/技术负责人", "新增计划行", "required", "pt_items[].sequence_no", "E2"),
            F("participation_item", "参加项目", "表头", "识别PT项目", "技术负责人", "选择项目", "required", "pt_items[].participation_item", clause="CX-30 4.2.4.1"),
            F("test_method", "检测方法", "表头", "关联方法", "技术负责人", "选择项目", "required", "pt_items[].test_method", assoc=["方法"]),
            F("participation_year", "参加年度", "表头", "记录计划年度", "技术负责人", "年度策划", "required", "pt_items[].participation_year"),
            F("provider", "组织单位", "表头", "识别能力验证提供者", "技术负责人", "获取PT信息", "required", "pt_items[].provider", assoc=["外部服务"]),
            F("remarks", "备注", "表头", "补充限制或状态", "技术负责人", "有补充", "optional", "pt_items[].remarks"),
            F("responsible_person_participants", "计划责任人/实验人员", "原表无独立列", "记录责任和参与人员", "技术负责人", "计划分派", "conditional", "pt_items[].responsible_person/participants[]", "E3", "CX-30 4.2.3,4.2.4.4", ["人员"]),
            F("approved_by_date", "批准人/日期", "原表无独立栏", "记录批准责任和时点", "实验室主任", "报名/执行前", "required", "approved_by_date", "E3", "CX-30 4.2.3.1,4.2.7", ["权限"]),
            F("simulation_disclaimer", "CNAS状态/不替代真实PT声明", "原表无；SIM治理元数据", "防止SIM计划冒充真实PT", "质量负责人/技术负责人", "SIM筹备场景", "conditional", "governance_metadata.cnas_status/simulation_disclaimer", "E3", "冻结画像CNAS初次申请筹备", ["CNAS"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-19-01",
        "title": "记录归档登记表",
        "source_file_name": "19-01记录归档登记表.docx",
        "procedure": "XZTC/CX-19-2022",
        "fields": [
            F("sequence_no", "序号", "表头", "标识归档行", "系统/资料管理员", "新增归档行", "required", "archive_items[].sequence_no", "E2"),
            F("record_name", "记录名称", "表头", "识别归档记录", "资料管理员", "接收归档", "required", "archive_items[].record_name"),
            F("control_number", "控制编号", "表头", "关联受控编号", "资料管理员", "接收归档", "required", "archive_items[].control_number"),
            F("copy_count", "份数", "表头", "记录交接数量", "资料管理员", "清点交接", "required", "archive_items[].copy_count"),
            F("submitted_by", "呈交人", "表头", "记录呈交责任人", "实际呈交人", "交接", "required", "archive_items[].submitted_by"),
            F("archived_by", "归档人", "表头", "记录归档责任人", "资料管理员", "入档", "required", "archive_items[].archived_by", clause="CX-19 3.4"),
            F("archived_at", "归档日期", "表头", "记录归档时点", "资料管理员", "入档", "required", "archive_items[].archived_at"),
            F("record_set_link", "电子记录集关联", "原表无", "关联电子记录集", "资料管理员/系统管理员", "电子归档", "pending_carrier", "", "E0", "原表和程序未确定承接字段", ["电子记录"], "exclude_pending_carrier"),
            F("retrieval_test", "检索测试", "原表无", "证明记录可检索", "资料管理员", "治理验证", "pending_carrier", "", "E0", "属于运行验证，不属于本原表字段", ["系统验证"], "exclude_pending_carrier"),
            F("modification_rule", "追加式修改规则", "原表无", "证明修改保留原值", "资料管理员/系统管理员", "记录更正", "pending_carrier", "", "E0", "属于运行验证，不属于本原表字段", ["审计轨迹"], "exclude_pending_carrier"),
        ],
    },
    {
        "doc_number": "XZTC/BG-26-01",
        "title": "计算机软件登记表",
        "source_file_name": "26-01计算机软件登记表.doc",
        "procedure": "XZTC/CX-26-2022",
        "fields": [
            F("software_code", "软件编号", "表头", "唯一识别软件", "办公室", "安装使用后登记", "required", "software_items[].software_code"),
            F("software_name", "软件名称", "表头", "识别软件", "办公室", "登记", "required", "software_items[].software_name"),
            F("purchase_date", "购置日期", "表头", "记录购置时间", "办公室", "购置软件", "conditional", "software_items[].purchase_date"),
            F("custodian", "保管人", "表头", "记录保管责任", "办公室/资料管理员", "登记或移交", "required", "software_items[].custodian", clause="CX-26 3.2,4.5.2"),
            F("remarks", "备注", "表头", "记录许可/版本/借用补充", "办公室", "有补充", "optional", "software_items[].remarks"),
            F("system_version", "系统版本", "原表无", "记录计算机化系统版本", "系统管理员", "系统登记", "pending_carrier", "", "E0", "BG-26-01原表不承接系统验证", ["系统验证"], "exclude_pending_carrier"),
            F("validation_status", "适用性验证状态", "原表无", "记录系统投入使用前验证", "技术负责人/系统管理员", "投入使用或变更", "pending_carrier", "", "E0", "需独立计算机化系统验证载体", ["系统验证"], "exclude_pending_carrier"),
            F("backup", "备份记录", "原表无", "记录备份执行", "系统管理员", "备份", "pending_carrier", "", "E0", "不应塞入软件登记表备注", ["备份"], "exclude_pending_carrier"),
            F("restore_test", "恢复测试", "原表无", "证明恢复可用", "系统管理员/技术负责人", "恢复演练", "pending_carrier", "", "E0", "需独立恢复验证载体", ["恢复"], "exclude_pending_carrier"),
            F("limitation", "系统限制", "原表无", "记录限制和替代措施", "技术负责人/系统管理员", "识别限制", "pending_carrier", "", "E0", "承接载体未确定", ["系统失效替代"], "exclude_pending_carrier"),
        ],
    },
    {
        "doc_number": "XZTC/BG-11-01",
        "title": "供应商评价表",
        "source_file_name": "11-01供应商评价表.doc",
        "procedure": "XZTC/CX-11-2022",
        "fields": [],
    },
    {
        "doc_number": "XZTC/BG-03-01",
        "title": "仪器设备台账",
        "source_file_name": "03-01仪器设备台帐.doc",
        "procedure": "XZTC/CX-03-2022",
        "fields": [
            F("sequence_no", "序号", "表头", "标识设备行", "系统/设备管理员", "新增设备", "required", "equipment_items[].sequence_no", "E2"),
            F("equipment_code", "编号", "表头", "唯一识别设备", "设备管理员", "设备建档", "required", "equipment_items[].equipment_code", clause="CX-03 4.1.4"),
            F("equipment_name", "名称", "表头", "识别设备", "设备管理员", "设备建档", "required", "equipment_items[].equipment_name"),
            F("model_spec", "规格型号", "表头", "记录规格型号", "设备管理员", "设备建档", "required", "equipment_items[].model_spec"),
            F("manufacturer", "生产厂", "表头", "记录制造商", "设备管理员", "设备建档", "conditional", "equipment_items[].manufacturer"),
            F("factory_number", "出厂编号", "表头", "记录出厂编号", "设备管理员", "设备建档", "conditional", "equipment_items[].factory_number"),
            F("purchase_date", "购进日期", "表头", "记录购进日期", "设备管理员", "设备建档", "conditional", "equipment_items[].purchase_date"),
            F("accuracy", "扩展不确定度/最大允差/准确度等级", "表头", "记录适用准确度指标", "设备管理员/技术负责人", "建档或能力更新", "conditional", "equipment_items[].accuracy", clause="CX-03 4.1.4"),
            F("measurement_range", "测量范围", "表头", "记录量程", "设备管理员/技术负责人", "建档或能力更新", "required", "equipment_items[].measurement_range", clause="CX-03 4.1.4"),
            F("traceability_method", "溯源方式", "表头及填表说明", "记录计量溯源方式", "设备管理员", "确定溯源方式", "required", "equipment_items[].traceability_method"),
            F("remarks", "备注", "表头", "补充状态或限制", "设备管理员", "有补充", "optional", "equipment_items[].remarks"),
            F("responsible_person", "责任人", "原表无独立栏", "记录设备责任人", "设备管理员", "建档或责任变更", "pending_confirmation", "equipment_items[].responsible_person", "E3", "CX-03 4.1.4", ["人员"]),
            F("stability_specification", "稳定性", "原表无独立栏", "记录稳定性指标或关联主数据", "设备管理员/技术负责人", "技术指标更新", "pending_confirmation", "equipment_items[].stability_specification", "E3", "CX-03 4.1.4；承接方式待核", ["设备主数据"]),
            F("resolution_specification", "分辨率", "原表无独立栏", "记录分辨率指标或关联主数据", "设备管理员/技术负责人", "技术指标更新", "pending_confirmation", "equipment_items[].resolution_specification", "E3", "CX-03 4.1.4；承接方式待核", ["设备主数据"]),
        ],
    },
    {
        "doc_number": "XZTC/BG-04-03",
        "title": "仪器设备和标准物质期间核查记录表",
        "source_file_name": "04-03仪器设备和标准物质期间核查记录表.doc",
        "procedure": "XZTC/CX-04-2022",
        "fields": [
            F("equipment_name", "名称", "表头“名称”", "识别核查对象", "设备管理员", "开展核查", "required", "equipment_name"),
            F("model_spec", "型号规格", "表头“型号规格”", "记录对象规格", "设备管理员", "开展核查", "conditional", "model_spec"),
            F("equipment_code", "编号", "表头“编号”", "关联设备/标物编号", "设备管理员", "开展核查", "required", "equipment_code"),
            F("check_basis", "核查依据", "表头“核查依据”", "关联受控核查依据", "设备管理员/技术负责人", "制定/实施核查", "required", "check_basis", clause="CX-04 4.3-4.4", assoc=["作业指导书"]),
            F("check_resources", "核查所用仪器设备或标准物质", "表头", "记录核查资源", "设备管理员/核查人员", "实施核查", "required", "check_resources", assoc=["设备/标准物质"]),
            F("check_personnel", "核查人员", "表头", "记录实施人员", "核查人员", "实施核查", "required", "check_personnel", clause="CX-04 4.6-4.7", assoc=["人员"]),
            F("process_record", "核查过程记录", "主体过程区", "记录核查过程", "设备管理员", "实施核查", "required", "process_record"),
            F("recorder", "记录人（设备管理员）", "过程区签核", "记录过程责任人", "设备管理员", "完成过程记录", "required", "recorder"),
            F("record_date", "记录日期", "过程区签核", "记录过程时点", "设备管理员", "完成过程记录", "required", "record_date"),
            F("result_judgement", "核查结果判定", "结果区", "形成核查结论", "核查人员/技术负责人复核", "核查收口", "required", "result_judgement"),
            F("checkers", "核查人员签名", "结果区签核", "核查责任签核", "核查人员", "判定完成", "required", "checkers", assoc=["权限"]),
            F("check_date", "核查日期", "结果区签核", "记录核查时点", "核查人员", "判定完成", "required", "check_date"),
            F("reviewer_opinion", "审核人意见", "审核区", "记录审核意见", "技术负责人", "审核", "required", "reviewer_opinion", clause="CX-04 3.2"),
            F("reviewer", "审核人签名", "审核区", "审核责任签核", "技术负责人", "审核", "required", "reviewer", assoc=["权限"]),
            F("review_date", "审核日期", "审核区", "记录审核时点", "技术负责人", "审核", "required", "review_date"),
            F("check_method", "核查方法", "当前schema硬编码；基础原表无独立栏", "具体核查方法", "技术负责人", "制定核查", "pending_evidence", "", "E0", "须回链具体作业指导书", ["技术依据"], "block_until_basis"),
            F("acceptance_criteria", "判定标准/限值", "当前schema硬编码；基础原表无独立栏", "具体判定准则", "技术负责人", "制定核查", "pending_evidence", "", "E0", "须回链具体作业指导书/方法正文", ["技术依据"], "block_until_basis"),
            F("measurement_data", "测量数据/单位/计算", "当前schema通用五列", "设备或标物专属数据结构", "核查人员", "实施核查", "pending_evidence", "", "E0", "19变体未逐一建立数据列、单位和计算依据", ["技术记录"], "block_until_basis"),
        ],
    },
]


def build_supplier_fields() -> list[dict[str, Any]]:
    fields = [
        F("evaluation_type", "评价类型", "原件含供应品/计量服务两个子表", "区分评价对象类型", "评价组织人", "新建评价", "required", "evaluation_type", "E3", "原件结构差异", ["供应商"]),
        F("subject_name", "所购物品名称/服务名称", "两个子表页首", "识别评价对象", "设备管理员/采购员", "初评/再评价", "required", "subject_name"),
        F("supplier_name", "供应商", "两个子表页首", "识别供方", "设备管理员/采购员", "初评/再评价", "required", "supplier_name", assoc=["供应商"]),
        F("product_specification", "规格型号", "供应品子表页首", "记录供应品规格", "设备管理员/采购员", "供应品评价", "conditional", "product_specification"),
        F("address", "地址", "两个子表页首", "记录供方地址", "设备管理员/采购员", "初评", "required", "address"),
        F("phone", "联系电话", "两个子表页首", "记录供方联系方式", "设备管理员/采购员", "初评", "required", "phone"),
        F("evaluator_date", "评价人/评价日期", "两个子表签核", "记录评价责任和时点", "实际评价人", "评价完成", "required", "evaluator/evaluation_date", assoc=["权限"]),
    ]
    supply = [
        ("delivery_capability", "生产规模/交付进度/履约能力符合"),
        ("supply_stability", "货源稳定"),
        ("product_quality", "供货质量符合"),
        ("packaging_transport", "包装运输质量符合"),
        ("technical_indicators", "技术指标符合标准"),
        ("price_reasonable", "价格合理"),
        ("quality_price_other", "质量与价格其他"),
        ("service_delivery", "服务热情、按时按量交货"),
        ("customer_contact", "及时联系并收集意见"),
        ("after_sales", "售后服务及时有效"),
        ("service_other", "服务与信誉其他"),
        ("technology_process", "技术和生产工艺先进"),
        ("management_control", "管理制度能控制产品质量"),
        ("technology_other", "技术与管理其他"),
        ("facilities_suitable", "设备设施满足产品要求"),
        ("facilities_other", "设备设施其他"),
        ("business_scope", "生产经营范围符合"),
        ("licenses", "生产许可证/准用证"),
        ("iso9000", "ISO 9000认证"),
        ("iso17025", "ISO/IEC 17025认可"),
        ("metrology_authorization", "法定计量认证/认可及附表"),
        ("quality_assurance_other", "质量保证能力其他"),
    ]
    for key, name in supply:
        required = "optional" if key.endswith("_other") else "required"
        fields.append(
            F(
                f"supply_{key}",
                name,
                "供应品子表对应评价项",
                f"评价供应品供方：{name}",
                "设备管理员/采购员",
                "供应商初评或年度再评价",
                required,
                f"supply_checks.{key}",
                assoc=["供应商评价"],
            )
        )
    calibration = [
        ("legal_qualification", "具备检定/校准机构法律资质", "required"),
        ("scope_match", "本实验室设备在其能力范围内", "required"),
        ("price_reasonable", "价格合理", "required"),
        ("quality_price_other", "质量与价格其他", "optional"),
        ("timely_service", "服务热情、按时检定", "required"),
        ("customer_contact", "及时联系、收集意见并满足要求", "required"),
        ("service_other", "服务与信誉其他", "optional"),
        ("other", "其他说明", "optional"),
        ("legal_attachment", "法律资质文件附件", "required"),
        ("scope_attachment", "能力范围附件", "required"),
        ("approval_opinion", "审批意见", "required"),
        ("technical_signature_date", "技术负责人/日期", "required"),
        ("lab_director_approval_date", "实验室主任批准/日期", "required"),
    ]
    for key, name, required in calibration:
        level = "E3" if key == "lab_director_approval_date" else "E1"
        clause = "CX-11 4.2.5.2；原表未见独立栏" if level == "E3" else "计量服务子表对应评价项"
        mapping = (
            f"attachments.{key.removesuffix('_attachment')}"
            if key.endswith("_attachment")
            else f"calibration_checks.{key}"
        )
        fields.append(
            F(
                f"calibration_{key}",
                name,
                "计量服务子表对应评价/签核项",
                f"评价计量服务供方：{name}",
                "设备管理员/采购员；签核按字段",
                "计量服务初评或年度再评价",
                required,
                mapping,
                level,
                clause,
                ["计量服务供应商"],
            )
        )
    return fields


next(spec for spec in RECORD_SPECS if spec["doc_number"] == "XZTC/BG-11-01")["fields"] = build_supplier_fields()


LEGACY_KEYS: dict[str, list[str]] = {
    "XZTC/BG-09-01": ["site", "standard", "sampling", "cma_status", "cnas_status", "business_date"],
    "XZTC/BG-28-01": ["site", "sampling", "retention", "sample_id", "business_date"],
    "XZTC/BG-22-02": ["site", "result", "standard", "authorization", "business_date"],
    "XZTC/BG-29-02": ["report_id", "standard", "cma_mark", "cnas_mark", "business_date"],
    "XZTC/BG-02-01": ["sites", "result", "business_date"],
    "XZTC/BG-30-01": ["status", "methods", "business_date"],
    "XZTC/BG-30-03": ["status", "result", "cnas_status", "business_date"],
    "XZTC/BG-19-01": ["record_set", "retrieval_test", "modification_rule", "business_date"],
    "XZTC/BG-26-01": ["system", "backup", "restore_test", "limitation", "business_date"],
    "XZTC/BG-11-01": ["supplier", "result", "business_date"],
    "XZTC/BG-03-01": ["status", "equipment_ids", "business_date"],
    "XZTC/BG-04-03": ["equipment", "result", "business_date"],
}


VARIANT_IDENTITY: dict[str, str] = {
    "04-03仪器设备和标准物质期间核查记录表-测金仪.doc": "测金仪",
    "04-03仪器设备和标准物质期间核查记录表-电子天平-TP02.doc": "电子天平-TP02",
    "04-03仪器设备和标准物质期间核查记录表-电子天平-TP03.doc": "电子天平-TP03",
    "04-03仪器设备和标准物质期间核查记录表-电子天平-TP04.doc": "电子天平-TP04",
    "04-03仪器设备和标准物质期间核查记录表-紫外.doc": "紫外",
    "04-03仪器设备和标准物质期间核查记录表-红外光谱仪.doc": "红外光谱仪",
    "04-03仪器设备和标准物质期间核查记录表.-折射仪-ZSY01doc.doc": "折射仪-ZSY01doc",
    "04-03仪器设备和标准物质期间核查记录表.-折射仪-ZSY02doc.doc": "折射仪-ZSY02doc",
    "04-03仪器设备和标准物质期间核查记录表.doc": "通用未指定对象",
    "04-03仪器设备和标准物质期间核查记录表合成红宝石标样.doc": "合成红宝石标样",
    "04-03仪器设备和标准物质期间核查记录表尖晶石标样.doc": "尖晶石标样",
    "04-03仪器设备和标准物质期间核查记录表金标片G05.doc": "金标片G05",
    "04-03仪器设备和标准物质期间核查记录表金标片G06.doc": "金标片G06",
    "04-03仪器设备和标准物质期间核查记录表金标片G08.doc": "金标片G08",
    "04-03仪器设备和标准物质期间核查记录表金标片G09.doc": "金标片G09",
    "04-03仪器设备和标准物质期间核查记录表金标片G10.doc": "金标片G10",
    "04-03仪器设备和标准物质期间核查记录表银标片G04.doc": "银标片G04",
    "04-03仪器设备和标准物质期间核查记录表银标片G18.doc": "银标片G18",
    "04-03仪器设备和标准物质期间核查记录表锆石标样.doc": "锆石标样",
}


VERIFIABLE_BG04_FIELDS = [
    "equipment_name",
    "model_spec",
    "equipment_code",
    "check_basis",
    "check_resources",
    "check_personnel",
    "process_record",
    "recorder",
    "record_date",
    "result_judgement",
    "checkers",
    "check_date",
    "reviewer_opinion",
    "reviewer",
    "review_date",
]
INSUFFICIENT_BG04_FIELDS = [
    {
        "field_key": "check_method",
        "reason": "源表可能在过程区描述方法，但未建立到具体作业指导书的逐字段引用",
    },
    {
        "field_key": "acceptance_criteria",
        "reason": "判定准则/限值必须回链具体作业指导书或方法正文，不能由表格预填值自证",
    },
    {
        "field_key": "measurement_data",
        "reason": "19个对象的数据列、单位、重复次数、计算和判定限不同，通用五列不能证明保真",
    },
]


def current_rows() -> dict[tuple[str, str], dict[str, str]]:
    doc_numbers = ",".join(f"'{spec['doc_number']}'" for spec in RECORD_SPECS)
    rows = mysql(
        "SELECT doc_number,source_file_name,source_file_sha1,"
        "LEFT(SHA2(field_schema,256),12),COALESCE(retention_period,''),status,review_status "
        "FROM record_form_templates WHERE soft_delete=0 AND trial_batch IS NULL "
        f"AND doc_number IN ({doc_numbers}) ORDER BY doc_number,source_file_sha1;"
    )
    return {
        (row[0], row[2]): {
            "doc_number": row[0],
            "source_file_name": row[1],
            "source_file_sha1": row[2],
            "schema_hash_prefix": row[3],
            "retention_period": row[4],
            "status": row[5],
            "review_status": row[6],
        }
        for row in rows
    }


def flattened_schema_keys() -> dict[tuple[str, str], list[str]]:
    doc_numbers = ",".join(f"'{spec['doc_number']}'" for spec in RECORD_SPECS)
    query = (
        "SELECT doc_number,source_file_sha1,GROUP_CONCAT(field_key ORDER BY field_key SEPARATOR ',') FROM ("
        "SELECT t.doc_number,t.source_file_sha1,j.field_key FROM record_form_templates t "
        "JOIN JSON_TABLE(CAST(t.field_schema AS JSON), '$[*]' COLUMNS(field_key VARCHAR(100) PATH '$.key', cols JSON PATH '$.columns')) j "
        f"WHERE t.soft_delete=0 AND t.trial_batch IS NULL AND t.doc_number IN ({doc_numbers}) "
        "UNION ALL "
        "SELECT t.doc_number,t.source_file_sha1,CONCAT(j.field_key,'.',c.child_key) FROM record_form_templates t "
        "JOIN JSON_TABLE(CAST(t.field_schema AS JSON), '$[*]' COLUMNS(field_key VARCHAR(100) PATH '$.key', cols JSON PATH '$.columns')) j "
        "JOIN JSON_TABLE(j.cols, '$[*]' COLUMNS(child_key VARCHAR(100) PATH '$.key')) c "
        f"WHERE t.soft_delete=0 AND t.trial_batch IS NULL AND t.doc_number IN ({doc_numbers})"
        ") f GROUP BY doc_number,source_file_sha1 ORDER BY doc_number,source_file_sha1;"
    )
    return {(row[0], row[1]): row[2].split(",") for row in mysql(query)}


def duplicate_groups() -> list[dict[str, Any]]:
    rows = mysql(
        "SELECT SHA2(field_schema,256),doc_number,name FROM record_form_templates "
        "WHERE soft_delete=0 AND trial_batch IS NULL ORDER BY SHA2(field_schema,256),doc_number,name;"
    )
    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    for schema_hash, doc_number, name in rows:
        grouped[schema_hash].append({"doc_number": doc_number, "name": name})
    return [
        {"schema_sha256": key, "member_count": len(value), "members": value}
        for key, value in grouped.items()
        if len(value) > 1
    ]


def source_inventory() -> dict[str, Any]:
    files = [path for path in RECORD_ROOT.rglob("*") if path.is_file()]
    extensions = Counter(path.suffix.lower().lstrip(".") for path in files)
    return {
        "root": str(RECORD_ROOT.relative_to(ROOT)),
        "file_count": len(files),
        "extension_counts": dict(sorted(extensions.items())),
    }


def build_snapshot(rows: dict[tuple[str, str], dict[str, str]]) -> dict[str, Any]:
    summary = mysql(
        "SELECT SUM(group_size),COUNT(*),SUM(group_size>1),"
        "SUM(CASE WHEN group_size>1 THEN group_size ELSE 0 END) FROM "
        "(SELECT SHA2(field_schema,256) h,COUNT(*) group_size FROM record_form_templates "
        "WHERE soft_delete=0 AND trial_batch IS NULL GROUP BY h) g;"
    )[0]
    total_templates = mysql(
        "SELECT COUNT(*),SUM(trial_batch IS NULL),SUM(trial_batch='SIM-GOV-R2-20260719') "
        "FROM record_form_templates WHERE soft_delete=0;"
    )[0]
    instances = mysql(
        "SELECT COUNT(*),SUM(t.trial_batch IS NULL),"
        "SUM(t.trial_batch='SIM-GOV-R2-20260719') FROM record_form_instances i "
        "JOIN record_form_templates t ON t.id=i.template_id;"
    )[0]
    baseline_target_instances = mysql(
        "SELECT COUNT(*) FROM record_form_instances i JOIN record_form_templates t ON t.id=i.template_id "
        "WHERE t.trial_batch IS NULL AND t.doc_number IN "
        "('XZTC/BG-09-01','XZTC/BG-28-01','XZTC/BG-22-02','XZTC/BG-29-02',"
        "'XZTC/BG-02-01','XZTC/BG-30-01','XZTC/BG-30-03','XZTC/BG-19-01',"
        "'XZTC/BG-26-01','XZTC/BG-11-01','XZTC/BG-03-01','XZTC/BG-04-03');"
    )[0][0]
    captured = mysql("SELECT DATE_FORMAT(NOW(),'%Y-%m-%dT%H:%i:%s');")[0][0]
    return {
        "schema_version": "1.0",
        "rehearsal_run_id": "SIM-GOV-R2-20260719",
        "environment": "8013-main-read-only",
        "database_container": CONTAINER,
        "captured_at_mysql": captured,
        "write_performed": False,
        "record_source_inventory": source_inventory(),
        "template_population": {
            "all_non_deleted": int(total_templates[0]),
            "current_baseline_trial_batch_null": int(total_templates[1]),
            "second_round_sim_fixtures": int(total_templates[2]),
            "baseline_distinct_exact_schema": int(summary[1]),
            "baseline_repeated_schema_groups": int(summary[2]),
            "baseline_repeated_schema_members": int(summary[3]),
        },
        "instance_population": {
            "all_instances": int(instances[0]),
            "instances_linked_to_current_baseline_templates": int(instances[1]),
            "instances_linked_to_second_round_sim_fixtures": int(instances[2]),
            "instances_linked_to_twelve_focus_current_templates": int(baseline_target_instances),
        },
        "focus_template_rows": sorted(rows.values(), key=lambda row: (row["doc_number"], row["source_file_sha1"])),
        "duplicate_schema_groups": duplicate_groups(),
        "interpretation": [
            "145项现用基线模板与7项第二轮专用SIM夹具分开计算",
            "145项现用基线仍为94种精确schema、13个重复组、64个重复成员",
            "8013中12项重点现用模板没有历史实例；因此孤儿键迁移只能形成零实例预览规则，不得伪造迁移结果",
            "103项实例全部关联第二轮专用SIM夹具，不能替代现用145项模板逐字段保真验证",
        ],
    }


def add_source_metadata(
    spec: dict[str, Any],
    current: dict[tuple[str, str], dict[str, str]],
    schema_keys: dict[tuple[str, str], list[str]],
) -> dict[str, Any]:
    source_path = find_source(spec["source_file_name"])
    source_sha1 = sha(source_path, "sha1")
    current_row = current[(spec["doc_number"], source_sha1)]
    record = {
        "doc_number": spec["doc_number"],
        "title": spec["title"],
        "object_identity": spec["title"],
        "identity_key": f"{spec['doc_number']}+{source_sha1}+{spec['title']}",
        "source_file": str(source_path.relative_to(ROOT)),
        "source_file_sha1": source_sha1,
        "source_file_sha256": sha(source_path),
        "source_text_sha256": extract_text_hash(source_path),
        "procedure": spec["procedure"],
        "current_template": {
            "source_file_sha1": current_row["source_file_sha1"],
            "schema_hash_prefix": current_row["schema_hash_prefix"],
            "field_keys_flattened": schema_keys[(spec["doc_number"], source_sha1)],
            "status": current_row["status"],
            "review_status": current_row["review_status"],
            "retention_period": current_row["retention_period"],
        },
        "fields": [],
    }
    for item in spec["fields"]:
        expanded = dict(item)
        if not expanded["associations"]:
            expanded["associations"] = ["未识别到可证直接关联；不据此假定无关联"]
        expanded["source_file"] = record["source_file"]
        expanded["source_file_sha256"] = record["source_file_sha256"]
        record["fields"].append(expanded)
    return record


def build_variants(
    current: dict[tuple[str, str], dict[str, str]],
    schema_keys: dict[tuple[str, str], list[str]],
) -> list[dict[str, Any]]:
    variants: list[dict[str, Any]] = []
    for name, object_identity in VARIANT_IDENTITY.items():
        source_path = find_source(name)
        source_sha1 = sha(source_path, "sha1")
        current_row = current[("XZTC/BG-04-03", source_sha1)]
        variants.append(
            {
                "doc_number": "XZTC/BG-04-03",
                "source_file": str(source_path.relative_to(ROOT)),
                "source_file_sha1": source_sha1,
                "source_file_sha256": sha(source_path),
                "source_text_sha256": extract_text_hash(source_path),
                "object_identity": object_identity,
                "identity_key": f"XZTC/BG-04-03+{source_sha1}+{object_identity}",
                "object_identity_status": "source_form_identity_only_not_sim_equipment",
                "current_schema_hash_prefix": current_row["schema_hash_prefix"],
                "current_schema_keys": schema_keys[("XZTC/BG-04-03", source_sha1)],
                "verifiable_fields": VERIFIABLE_BG04_FIELDS,
                "evidence_insufficient_fields": INSUFFICIENT_BG04_FIELDS,
                "technical_values_authorized_for_candidate": False,
            }
        )
    return sorted(variants, key=lambda row: row["source_file"])


def orphan_preview(records: list[dict[str, Any]]) -> dict[str, Any]:
    previews = []
    for record in records:
        candidate_keys = {field["field_key"] for field in record["fields"] if field["candidate_action"] != "exclude_pending_carrier"}
        mappings = []
        for old_key in LEGACY_KEYS[record["doc_number"]]:
            direct = old_key in candidate_keys
            mappings.append(
                {
                    "legacy_key": old_key,
                    "action": "direct_map_if_type_compatible" if direct else "manual_map_or_governance_metadata",
                    "candidate_key": old_key if direct else "",
                    "write_allowed": False,
                    "reason": "8013现用重点模板无旧实例；仅生成规则，待真实旧实例冻结后逐值预览",
                }
            )
        previews.append({"doc_number": record["doc_number"], "rules": mappings})
    return {
        "fresh_8013_baseline_instance_count": 0,
        "preview_only": True,
        "database_write_performed": False,
        "identity_rule": "doc_number+source_file_sha1+object_identity",
        "unknown_key_rule": "不得丢弃、不得塞入备注；标为orphan_pending_review并保留原值/实例/模板/hash",
        "type_mismatch_rule": "不自动强转；生成old_value/new_candidate_key/type_difference预览",
        "nested_rule": "数组和子表逐行逐列映射；未确认粒度时阻断",
        "locked_instance_rule": "旧实例保持只读，不原地覆盖；新版本建立新实例或显式迁移副本",
        "record_previews": previews,
    }


def markdown(candidate: dict[str, Any]) -> str:
    lines = [
        "# SIM 记录模板语义覆盖候选 v0.2",
        "",
        "> 文件状态：`draft_candidate`  ",
        "> 演练编号：`SIM-GOV-R2-20260719`  ",
        "> 性质：第二轮逐字段候选；只读复算现用 161 个源文件和 8013 当前模板，不写 8013、不改现用文件。  ",
        "> 边界：旧轮 v0.2 只作字段回归素材；本文件重新固定当前源 hash、8013 schema 和候选字段。尚待新的独立验证。",
        "",
        "## 1. 新鲜复算结论",
        "",
        "| 项目 | 数值 |",
        "|---|---:|",
    ]
    pop = candidate["fresh_recalculation"]["template_population"]
    inv = candidate["fresh_recalculation"]["record_source_inventory"]
    inst = candidate["fresh_recalculation"]["instance_population"]
    lines.extend(
        [
            f"| 现用记录源文件 | {inv['file_count']} |",
            f"| 8013 非删除模板总数 | {pop['all_non_deleted']} |",
            f"| 现用基线模板（trial_batch 为空） | {pop['current_baseline_trial_batch_null']} |",
            f"| 第二轮专用 SIM 夹具模板 | {pop['second_round_sim_fixtures']} |",
            f"| 现用基线精确 schema | {pop['baseline_distinct_exact_schema']} |",
            f"| 重复 schema 组/成员 | {pop['baseline_repeated_schema_groups']} / {pop['baseline_repeated_schema_members']} |",
            f"| 重点现用模板实例 | {inst['instances_linked_to_twelve_focus_current_templates']} |",
            "",
            "重点现用模板当前没有实例，因此本轮只能给出旧实例孤儿键的零实例迁移规则，不能冒充已完成迁移。103 项现有 SIM 实例属于 7 项专用夹具，不能替代 145 项现用模板保真验证。",
            "",
            "## 2. 逐字段矩阵",
        ]
    )
    for record in candidate["record_candidates"]:
        lines.extend(
            [
                "",
                f"### {record['doc_number']} {record['title']}",
                "",
                f"- 身份：`{record['identity_key']}`",
                f"- 源文件：`{record['source_file']}`",
                f"- SHA256：`{record['source_file_sha256']}`",
                f"- 当前 schema：`{record['current_template']['schema_hash_prefix']}`",
                f"- 候选字段：{len(record['fields'])}",
                "",
                "| 字段 | 来源定位/依据 | 业务含义 | 岗位 | 触发/必填 | 更正与关联 | 保存依据状态 | LIMS 映射 | 来源等级/处置 |",
                "|---|---|---|---|---|---|---|---|---|",
            ]
        )
        for item in record["fields"]:
            def esc(value: Any) -> str:
                if isinstance(value, list):
                    value = "；".join(value)
                return str(value).replace("|", "\\|").replace("\n", " ")

            lines.append(
                "| "
                + " | ".join(
                    [
                        esc(item["field_name"] + " `" + item["field_key"] + "`"),
                        esc(item["source_locator"] + "；" + item["procedure_clause_or_frozen_fact"]),
                        esc(item["business_meaning"]),
                        esc(item["responsible_role"]),
                        esc(item["trigger"] + "；" + item["required_rule"]),
                        esc(item["correction_rule"] + "；关联：" + "、".join(item["associations"])),
                        esc(item["retention_basis_status"]),
                        esc(item["lims_mapping"] or "阻断，不映射"),
                        esc(item["source_level"] + "/" + item["candidate_action"]),
                    ]
                )
                + " |"
            )
    lines.extend(
        [
            "",
            "## 3. BG-04-03 的 19 个同号变体",
            "",
            "身份固定为 `doc_number + source_file_sha1 + object_identity`。对象名称和源表预填值只用于区分源表，不作为 SIM 设备事实。",
            "",
            "| 对象 | SHA1 | SHA256 | 当前 schema | 可证字段 | 证据不足字段 |",
            "|---|---|---|---|---|---|",
        ]
    )
    for variant in candidate["bg_04_03_variants"]:
        lines.append(
            f"| {variant['object_identity']} | `{variant['source_file_sha1']}` | "
            f"`{variant['source_file_sha256']}` | `{variant['current_schema_hash_prefix']}` | "
            f"{', '.join(variant['verifiable_fields'])} | "
            f"{', '.join(item['field_key'] for item in variant['evidence_insufficient_fields'])} |"
        )
    lines.extend(
        [
            "",
            "三类证据不足统一保持阻断：具体核查方法、具体判定准则/限值、设备或标物专属测量数据结构（含单位、重复次数、计算和判定）。源表中存在预填数字也不能替代作业指导书或方法正文。",
            "",
            "## 4. 旧实例孤儿键迁移预览",
            "",
            "- 当前 8013 的 12 项重点现用模板实例数：0。",
            "- 旧实例不原地覆盖；未知键保留原值并标记 `orphan_pending_review`。",
            "- 类型不匹配不自动强转；数组/子表按原粒度映射。",
            "- 同号模板必须先按 `doc_number+source_file_sha1+object_identity` 选定身份。",
            "- 本候选只给规则，`database_write_performed=false`。",
            "",
            "完整逐键规则见同名 JSON 的 `orphan_key_migration_preview`。",
            "",
            "## 5. 待独立验证",
            "",
            "1. 重新计算 161/145/94/13/64 和 19 个变体 hash。",
            "2. 对 12 项记录逐字段回看当前源 Word/Doc 和程序条款。",
            "3. 检查每个字段的来源、业务含义、岗位、触发、必填、更正、关联、保存依据状态、LIMS 映射和来源等级。",
            "4. 反向检查 evidence_insufficient 项没有技术值、单位、计算或限值。",
            "5. 检查 8013 中重点模板实例数仍为 0，孤儿键规则没有写库。",
            "6. 验证通过前保持 `draft_candidate`；本文件不自行推进状态。",
            "",
        ]
    )
    return "\n".join(lines)


def main() -> None:
    current = current_rows()
    keys = flattened_schema_keys()
    snapshot = build_snapshot(current)
    snapshot_path = CANDIDATE_DIR / "8013记录模板新鲜复算-v0.2.json"
    snapshot_path.write_text(json.dumps(snapshot, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    records = [add_source_metadata(spec, current, keys) for spec in RECORD_SPECS]
    variants = build_variants(current, keys)
    candidate = {
        "schema_version": "2.0",
        "candidate_file_id": "R2-FILE-003",
        "title": "SIM记录模板语义覆盖候选",
        "version": "v0.2",
        "file_state": "draft_candidate",
        "effect_scope": "sim_only_noncontrolled_candidate",
        "rehearsal_run_id": "SIM-GOV-R2-20260719",
        "source_material": {
            "path": "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/.team/交接箱/2026-07-19-LIMSzhj完整沙箱持续治理演练/候选整改/记录模板逐字段治理候选-v0.2.md",
            "sha256": "e33a1875d87f30f40f060d902ede97460224368b9d106a2d0052fcee54ea1c78",
            "role": "source_material_only_no_inherited_conclusion",
        },
        "fresh_snapshot": {
            "path": "候选覆盖层/8013记录模板新鲜复算-v0.2.json",
            "sha256": sha(snapshot_path),
        },
        "fresh_recalculation": snapshot,
        "record_candidates": records,
        "bg_04_03_variants": variants,
        "orphan_key_migration_preview": orphan_preview(records),
        "verification": {
            "status": "pending_independent_validation",
            "self_verified": False,
            "independent_result_links": [],
        },
        "replay": {"status": "not_run", "evidence_links": []},
        "write_boundary": {
            "8013_database_write": False,
            "current_file_write": False,
            "product_code_write": False,
        },
    }
    json_path = CANDIDATE_DIR / "SIM-记录模板语义覆盖候选-v0.2.json"
    md_path = CANDIDATE_DIR / "SIM-记录模板语义覆盖候选-v0.2.md"
    json_path.write_text(json.dumps(candidate, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md_path.write_text(markdown(candidate), encoding="utf-8")
    print(
        json.dumps(
            {
                "candidate_json": str(json_path),
                "candidate_markdown": str(md_path),
                "record_count": len(records),
                "field_count": sum(len(record["fields"]) for record in records),
                "variant_count": len(variants),
                "snapshot_sha256": sha(snapshot_path),
                "candidate_json_sha256": sha(json_path),
                "candidate_markdown_sha256": sha(md_path),
            },
            ensure_ascii=False,
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
