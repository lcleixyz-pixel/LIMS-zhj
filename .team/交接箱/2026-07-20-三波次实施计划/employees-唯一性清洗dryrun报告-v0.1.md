# employees 唯一性清洗 dry-run 报告

- 生成时间：2026-07-20T18:48:29+08:00
- 模式：dry-run
- 写库：否
- 在职/未删员工数：11
- 空员工编号：0
- 空邮箱：10
- 重复编号组：0
- 重复邮箱组：0
- 可上唯一索引：no

> 目标库：8010 现用库（jewelry-qms-db-1）只读 dry-run，未执行 --apply，database_write_performed=0。
> 已知 blank collision：空邮箱 10 条（空串/空值在 UNIQUE(email) 下会互相碰撞），上唯一索引前须先 `normalize_empty_string_to_null`。

## 重复员工编号

（无）

## 重复邮箱

（无）

## 空串碰撞（上 UNIQUE 前须归一 NULL）

- 空编号行：0
- 空邮箱行：10

## 建议动作

- {"kind":"blank_email","count":10,"action":"normalize_empty_string_to_null"}
