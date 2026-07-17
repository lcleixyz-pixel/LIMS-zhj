# B6 快照说明

执行任何写入前，使用 `mysqldump --single-transaction --quick` 生成完整快照，并单独导出
`employees`、`users`、`qms_positions`、`employee_appointments`。快照不得提交 Git、不得进入聊天，
必须生成 SHA256，并在另一临时库完成恢复验证。