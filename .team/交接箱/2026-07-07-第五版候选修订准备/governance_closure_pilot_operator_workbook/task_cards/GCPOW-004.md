# GCPOW-004 技术记录更正记录

- pilot_evidence_id: GCPE-004
- closure_item_id: GC-0080
- owner_role: 原记录人员/复核人员
- workbook_status: pending

## 需要补齐

- evidence_reference：填写可追溯证据名称、编号、文件路径或会议/培训/评审记录编号。
- evidence_summary：简述证据证明了什么，不得只写“已完成”。
- closure_comment：写明为什么该阻断项可关闭，必要时列出仍需跟踪事项。
- reviewer：填写实际复核人或责任岗位确认人。
- review_date：填写实际复核日期，格式建议 YYYY-MM-DD。
- evidence_status：试点证据真实形成并可追溯后才可由 pending 改为 completed。
- signature_status：岗位签核完成后才可由 pending 改为 completed。
- handoff_status：交接复核完成后才可由 pending 改为 completed。
- assigned_person：填写实际执行人；没有明确人员时不得关闭。
- reviewer：填写实际复核人或责任岗位确认人。
- actual_finish_date：填写实际完成日期，格式建议 YYYY-MM-DD。

## 复跑顺序

1. 补齐试点证据填写页和签核交接页。
2. 重新生成试点回填预览。
3. 重新生成源工作台补丁预演。
4. 补丁无阻断后，再由人工确认是否回填源工作台。

边界：不写数据库，不代表人工评审通过，不代表真实培训完成，不代表受控发布，不写入质量手册正文。
