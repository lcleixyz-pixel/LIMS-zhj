# governance_readiness_dashboard

用途：把第五版候选修订准备包的全部关键闸门汇总成一个只读治理就绪总览，方便用户、质量负责人和 LIMS 命令层同时判断下一步。

## 文件

- `governance_readiness_manifest.json`：机器可读清单。
- `00-治理就绪总览.md`：给人看的总览和关键阻断数。
- `01-总闸门清单.csv`：闸门级状态表。
- `02-人工处理任务清单.csv`：人工要处理的任务列表。
- `03-LIMS命令复核清单.md`：命令层复跑参数和期望状态。

## 当前结论

- readiness：blocked_by_governance_open_items
- ready_for_lims_apply：no
- 阻断任务：392

## 边界

- 本包只汇总现有候选文件、模板、评审、发布演练、学习实施和第二阶段复核状态，不写数据库。
- 本包不修改 human_review_pack、stage2_structured_review_workbench 或任何现用 Word 文件。
- 本包不代表人工评审通过、真实培训完成、受控发布或正式写库授权。
- 资质状态口径：已取得 CMA，CNAS 申请中；不得表述为已取得 CNAS。
- LIMS 当前导出的 2022 程序清单仍作为现行程序目录。
- jewelry-qms 仍为建设中系统，只进入实施计划、演练和治理准备材料，不写入质量手册正文。
