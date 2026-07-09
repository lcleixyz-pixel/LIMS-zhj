# 人审通过模拟包

文件状态：apply-rehearsal 专用模拟包，不是真实人工评审结果。

## 计数

- 条款评审模拟通过项：29
- 记录模板评审模拟通过项：26
- 05-02 归属模拟通过项：1
- apply 前闸门模拟通过项：11
- 总模拟决策项：67

## 使用边界

- 本包仅用于 qms:preimport-package --apply-rehearsal 非写库演练。
- 本包不代表真实人工评审、审核批准、受控发布或正式写库授权。
- 本包不修改 human_review_pack/，不得作为正式 --apply 的 --review-dir。
- 所有模拟决策均带 SIMULATED_APPROVAL_NOT_REAL_REVIEW 标识。
- 资质状态按已取得 CMA、CNAS 申请中处理；不得写成已取得 CNAS。

## 用途

本包只用于验证 LIMS 命令在“人审全部通过”条件下是否能完成 apply 前置检查、stage2 关系预检和安全边界判断。真实受控写库仍必须使用经人工评审后正式回填的 `human_review_pack/`。
