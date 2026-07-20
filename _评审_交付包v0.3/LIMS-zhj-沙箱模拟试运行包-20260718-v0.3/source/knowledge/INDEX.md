# Knowledge Index

当前索引为空，尚未录入标准、内部文件或案例卡片。

后续生成规则：

| 分区 | 索引键 | 目标 |
|---|---|---|
| 标准卡片 | `clause` + `basis` | 按条款号定位 ISO/IEC 17025、CNAS、CMA 要点 |
| 内部文件 | `doc_number` | 按受控文件编号定位本所体系文件结构化导出 |
| 案例卡片 | `clause_refs` + `topic` | 按条款和场景回查评审经验 |

`knowledge/internal/` 下的条目应由 jewelry-qms 结构化库导出命令生成，避免手工维护造成双源分叉。
