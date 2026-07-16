# G4-R5 生成文件清理草案 v0.1

> 草案，未执行。执行前需先备份数据库和 `public/uploads/record-form-pdf` / `runtime/record-form-test-link` 相关文件。

## 本地已发现的候选文件

```text
runtime/record-form-test-link/TEST-LINK-20260709-191600.json
public/uploads/record-form-pdf/30e3b4ae-13a9-41e3-8191-ee9b6b2ed3ef/TEST-LINK-20260709-191600-XZTC_BG-01-09_20260709191922.pdf
public/uploads/record-form-pdf/30e3b4ae-13a9-41e3-8191-ee9b6b2ed3ef/TEST-LINK-20260709-191600-XZTC_BG-01-09_20260709191608.pdf
public/uploads/record-form-pdf/ac076509-3578-4593-a1c2-3d8faa5ae7b4/TEST-LINK-20260709-191600-XZTC_BG-01-04_20260709191603.pdf
public/uploads/record-form-pdf/809adced-952c-4e73-ae16-b33b7e270ecd/TEST-LINK-20260709-191600-XZTC_BG-01-02_20260709191601.pdf
public/uploads/record-form-pdf/e9f53ee8-de5e-4251-b141-30dd6bebcfa9/TEST-LINK-20260709-191600-XZTC_BG-01-05_20260709191604.pdf
public/uploads/record-form-pdf/42ce8c56-8425-48e6-b97b-7767fa25fadf/TEST-LINK-20260709-191600-XZTC_BG-01-07_20260709191606.pdf
public/uploads/record-form-pdf/9eedda60-8958-480f-b67d-151e5c1935a0/TEST-LINK-20260709-191600-XZTC_BG-01-06_20260709191605.pdf
public/uploads/record-form-pdf/acdbc9e1-5f33-4b66-a76b-bab2ffea8f00/TEST-LINK-20260709-191600-XZTC_BG-01-08_20260709191607.pdf
public/uploads/record-form-pdf/338f4e68-f5ae-4a9b-8010-bd96fe126273/TEST-LINK-20260709-191600-XZTC_BG-01-01_20260709191600.pdf
public/uploads/record-form-pdf/38d9ac1b-0947-4415-85b8-f7fd0283fe37/TEST-LINK-20260709-191600-XZTC_BG-01-03_20260709191602.pdf
```

## 建议执行方式

1. 先备份数据库和上述文件。
2. 先执行 `cleanup-generated-test-data-draft.sql`。
3. 再删除文件名包含 `TEST-LINK-20260709-191600` 的 PDF 和 JSON。
4. 复核记录填报页面、法规候选池页面、审核准备页面均能正常打开。

## 不建议删除

`qms_document_assets` 中的“人员培训评价表”资产暂不纳入清理，因为它未带 `TEST-LINK` 测试标识，更像记录表格模板资产。若后续确认它也是生成污染，再单独列入清理。
