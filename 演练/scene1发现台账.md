# Scene1 发现台账 · 法规监视"一单一库"走查（赵姐）

- 判定口径：sandbox-pass 正常｜sandbox-noise 疑似降真/排练环境差异｜real-stack-required 需真栈（含真实种子数据/生产配置）复核｜product-defect 与环境无关的产品/流程缺陷
- 严重度：A 流程完全走不通｜B 主要功能受阻或数据错误｜C 提示不清/别扭｜D 改进建议
- 截图见 /home/claude/rehearsal/shots/scene1/

## 问题项

| 编号 | 现象（一句话） | 页面 URL | 复现步骤 | 判定 | 严重度 |
|---|---|---|---|---|---|
| F-01 | 登录后所有页面裸排版：侧边菜单整块糊在页面顶部、深蓝底蓝字不可读、正文被挤到页尾 | 全站（如 /dashboard/index、/planning/regulatory-candidates） | ①登录任意页 ②观察布局；curl 探资源：/static/css/qms.css 200，cdn.jsdelivr.net 的 bootstrap.min.css 不可达（沙箱无外网） | sandbox-noise（环境无公网致 CDN 失效） | B |
| F-02 | 前端 bootstrap/jquery 依赖公网 CDN 且无本地回退——实验室 QMS 常内网部署，真实离线环境将同样全站样式崩坏 | 全站页面模板（view 层引用 cdn.jsdelivr.net 3 个资源） | ①查看任意页源码 link/script 标签 ②断外网加载 | product-defect（架构隐患，建议静态资源本地化打包） | C（内网部署场景升 B） |
| F-03 | 法规候选池列表为空（"暂无法规候选"），走查主对象无从核验；确认状态=全部仍无一条 | /planning/regulatory-candidates | ①菜单 外部变化管理→法规候选池 ②筛选"全部" | real-stack-required（疑似排练库未跑初始化/种子数据，需带数据环境复核功能本身） | B |
| F-04 | 记录模板 0 条、填写记录 0 条，"模板试填存草稿"无法进行（新增模板超授权未做） | /record_form_template/index、/record_form_instance/index | ①菜单 记录填报→记录模板 ②看列表空态 ③填写记录页"选择模板填写"无模板可选 | real-stack-required（同上，种子数据缺失） | B |
| F-05 | 访问不存在的候选详情 URL 返回 200 且静默渲染成列表页，无 404/"记录不存在"提示，易误判"数据丢了" | /planning/regulatory-candidates/detail?id=1 | ①直接打开该 URL（库中无 id=1）②观察：地址栏仍是 detail?id=1，内容却是候选池列表 | product-defect（容错/提示缺失） | C |
| F-06 | 仪表盘「机构运营概览 · 2026-10-15」比假时钟设定的系统今天 2026-10-14 快一天（月份属正常范围） | /dashboard/index | ①登录 ②看仪表盘标题日期；对照剧本设定 10-14 | sandbox-noise（疑似假时钟注入与应用时区叠加的 ±1 天偏移，需环境侧核对） | C |
| F-07 | 系统内时间为 2026-10，菜单与页面仍写死「2025 运行确认 / 2025 年度记录运行确认」（链接参数 year=2025 硬编码），年份未跟随系统时间（疑似） | 菜单项 /record_form_instance/reviewDashboard?year=2025、/record_form_instance/index | ①看侧边菜单"记录填报"分组 ②看填写记录页顶部按钮 | product-defect（疑似年份硬编码；也可能有意指上年度，需产品确认口径） | C |
| F-08 | 找功能靠人肉扫长菜单：无站内搜索，且剧本/行业用语与系统命名不对齐（法规监视→外部变化管理、一单一库→外部依据+条款库、溯源链→追溯矩阵、表格模板→记录模板），首次使用者按名字找不到 | 全站导航（侧边菜单） | ①在菜单中检索"法规监视/一单一库/溯源链"字样 ②均无命中，需逐组展开猜含义 | product-defect（信息架构/命名与可发现性） | D |
| F-09 | 每页页脚暴露执行耗时调试信息（如 0.017216s，登录页即有），疑似框架调试模式痕迹 | 全站（含 /login/index） | ①打开任意页 ②看页脚版权行下方小字 | real-stack-required（需确认生产配置是否关闭调试输出） | D |

## 通过项（简要）

| 编号 | 现象（一句话） | 页面 URL | 复现步骤 | 判定 | 严重度 |
|---|---|---|---|---|---|
| P-01 | 登录流程顺畅，错误引导（"首次登录请联系管理员"）清楚，登录后直达仪表盘 | /login/index → /dashboard/index | admin/password 登录 | sandbox-pass | — |
| P-02 | 候选池页面职责与边界文案清晰："候选只供人工复核，不会自动修改体系文件…"、"no_match 不等于不适用"，符合一单一库"机器建议、人工定夺"定位 | /planning/regulatory-candidates | 打开候选池读页首说明 | sandbox-pass | — |
| P-03 | 变更事件页状态机与空态引导好：已登记→影响评估中→修订处理中→已关闭/不适用归档；空态注明"候选确认后在此形成事件，也可手工登记"并给"去候选池看看"回链 | /planning/change-events | 候选池→查看正式变更事件 | sandbox-pass | — |
| P-04 | "一单一库"链路各页互链自洽：外部依据（上传/查新/证据）→条款库→要素→追溯矩阵→策划中心总览，空态均给出下一步指引（"初始化策划骨架""先上传依据抽取条款"）；内置依据清单 5 条（CL01:2018、G001:2024、A015:2018、GB/T 27025-2019、总局 2023 年 21 号公告）与实验室双体系场景匹配 | /planning/sources、/planning/clauses、/planning/traceability、/planning/index | 依次打开四页对照引导文案 | sandbox-pass | — |
| P-05 | 假时钟月份一致：日历页「本月体系待办（2026年10月）」范围 2026-10-01~10-31，页脚版权 © 2026，与设定月份吻合 | /calendar/index | 打开本月待办 | sandbox-pass | — |

## 统计
- 发现总数：14（问题 9 + 通过 5）
- 判定分布：sandbox-pass 5｜sandbox-noise 2（F-01、F-06）｜real-stack-required 3（F-03、F-04、F-09）｜product-defect 4（F-02、F-05、F-07、F-08）
- 严重度分布：A 0｜B 3（F-01、F-03、F-04）｜C 4（F-02、F-05、F-06、F-07）｜D 2（F-08、F-09）
