# 珠宝检测实验室质量管理系统 (Jewelry QMS)

基于 CakePHP 2.x，面向 CMA/CNAS（ISO/IEC 17025）要求的实验室质量管理体系信息化。

> 工作区总文档见上级目录 [README.md](../README.md)。架构、部署、体系文件适配见 [docs/](../docs/)。

## 功能模块

| 模块 | 说明 |
|------|------|
| 文件控制 | 四层级（质量手册/程序文件/SOP/记录表格）、Word 上传、差异化审批、版本修订 |
| 内部审核 | 年度计划、审核日程、检查表、审核发现 |
| 管理评审 | 评审会议、决议事项跟踪 |
| CAPA | 纠正预防措施全生命周期 |
| 设备与校准 | 设备台账、校准记录、到期提醒 |
| 培训与能力 | 培训记录、能力确认与授权 |
| 供应商 | 供应商评价与合格名录 |
| 客户投诉 | 投诉受理与处理闭环 |
| 不符合工作 | 识别、评价、处置、验证 |

## 环境要求

- PHP 5.6+（推荐 7.4）
- MySQL 5.7+ / MariaDB
- Apache + mod_rewrite
- 扩展：mbstring, pdo_mysql, json

## 安装步骤

1. 将 `jewelry-qms` 部署到 Web 目录（如 `htdocs/jewelry-qms`）
2. 确保 `app/tmp` 可写（已创建 cache/logs/sessions 目录）
3. 导入数据库：
   ```bash
   mysql -u root -p < app/webroot/schema/jewelry_qms.sql
   ```
4. 修改 `app/Config/database.php` 中的数据库连接信息
5. 访问：`http://localhost/jewelry-qms/`
6. 默认登录：**admin** / **password**

## 审批规则（可在 `app/Config/core.php` 调整）

- 质量手册、程序文件：**编制 → 审核 → 批准**（三级）
- 作业指导书、记录表格：**编制 → 批准**（两级）

## 目录结构

```
jewelry-qms/
├── app/
│   ├── Config/          # 配置
│   ├── Controller/      # 控制器
│   ├── Model/           # 模型
│   ├── View/            # 视图（中文界面）
│   └── webroot/
│       ├── schema/      # SQL 初始化脚本
│       └── files/       # 上传文件存储
├── lib/Cake/            # CakePHP 框架
└── README.md
```

## 与现有检测业务系统对接

本系统独立部署，建议通过 REST API 或数据库只读视图共享：

- 人员、设备、客户基础数据
- 检测报告编号（用于投诉/不符合追溯）

## 后续定制

1. 在「文件模板管理」上传贵实验室现有 Word 模板（.docx）
2. 按 `doc_number` 编码规则批量导入现有体系文件清单
3. 配置各部门审批人（用户管理中设置 `is_approver`）

## 技术说明

- 与检测业务系统松耦合，避免数据孤岛
- U