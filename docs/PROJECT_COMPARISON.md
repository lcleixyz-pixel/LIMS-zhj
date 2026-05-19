# FlinkISO 与 FlinkISO Lite 对比说明

本文档记录工作区内两个参考项目的差异，作为选型与 `jewelry-qms` 定制的依据。

## 1. 基本信息

| 维度 | FlinkISO On-Premise | FlinkISO Lite |
|------|---------------------|---------------|
| 路径 | `flinkiso/flinkiso-ver-2x-on-premise/` | `flinkiso-lite-master/flinkiso-lite-master/` |
| 版本 | 2.2.42 | Lite（CakePHP 2.3.6） |
| 定位 | 商业本地部署 | 社区/轻量版 |
| 控制器数 | ~42 | ~98 |
| 模型数 | ~49 | ~131 |
| 数据库 | MyISAM + UUID | 多表，安装向导 |

## 2. 技术栈差异

| 能力 | On-Premise | Lite |
|------|------------|------|
| CakePHP | 2.10.24 | 2.3.6 |
| 文档在线编辑 | ONLYOFFICE | 无 |
| PDF 生成 | WkHtmlToPdf + PDFTk | 无 |
| 富文本 | TinyMCE | CKEditor |
| 安装 | SQL 手动导入 | Web 三阶段安装 |
| CAPA | 无独立模块 | 完整 |
| 培训/能力 | 简单 | 完整链路 |
| 供应商 | 简单 | 评价/再评/名录 |
| 通知 | 弱 | Notification 模块 |

## 3. 对珠宝检测实验室（17025）的匹配度

| 17025 相关要求 | 推荐借鉴来源 |
|----------------|--------------|
| 文件控制（深度） | On-Premise（审批、版本）+ 自研四层级 |
| CAPA | Lite |
| 内审 | 两者均有，Lite 更全 |
| 管评 | On-Premise MRM 模块 |
| 设备/校准 | Lite |
| 培训与能力授权 | Lite |
| 供应商/外部服务 | Lite |
| 客户投诉 | Lite |
| 不符合工作 | Lite |

**结论**：`jewelry-qms` 以 **Lite 为代码基础**，从 **On-Premise** 择期移植 ONLYOFFICE、PDF、记录锁定。

## 4. 本仓库中的使用方式

- **只读参考**：阅读 `flinkiso`、`flinkiso-lite-master` 中对应 Controller/Model/View。
- **运行调试**：需单独配置数据库与 Apache 虚拟目录（见 [DEPLOYMENT.md](DEPLOYMENT.md)）。
- **生产使用**：仅部署 `jewelry-qms`。
