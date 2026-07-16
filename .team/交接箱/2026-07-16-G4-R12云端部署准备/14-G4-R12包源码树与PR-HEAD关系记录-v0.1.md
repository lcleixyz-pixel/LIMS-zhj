# G4-R12 包源码树与 PR HEAD 关系记录 v0.1

日期：2026-07-16

## 结论

`20260716-g4r12-pr31` 候选包中的 `jewelry-qms/` 源码树，与当前集成分支 HEAD 中的 `jewelry-qms/` 源码树一致。

当前集成分支 HEAD 高于候选包生成时的提交，是因为后续只追加了 `.team/交接箱` 中的部署交接、PR 文案、C2 清单和发布清单材料；没有改变 `jewelry-qms/` 代码树。

因此：

- 不需要因为这些后续文档提交而重新打包应用源码和镜像；
- C1 Draft PR 可以使用当前 HEAD；
- C2 云端上传仍使用 `20260716-g4r12-pr31` 候选包；
- 审查时应区分“PR 审查 HEAD”和“部署包内源码树”。

## 当前 HEAD

```text
branch: codex/g4-r12-pr31-integrated-deploy-prep
HEAD:   3f4a06587687fd7675021fc181fd590be25aec99
```

## `jewelry-qms/` 树哈希核对

```text
HEAD:jewelry-qms      863cfe4e48649155f4b4294004fa976a7fbdb654
61266e22:jewelry-qms  863cfe4e48649155f4b4294004fa976a7fbdb654
780dd2bf:jewelry-qms  863cfe4e48649155f4b4294004fa976a7fbdb654
ff2689b3:jewelry-qms  863cfe4e48649155f4b4294004fa976a7fbdb654
3f4a0658:jewelry-qms  863cfe4e48649155f4b4294004fa976a7fbdb654
```

判断：上述提交的 `jewelry-qms/` Git 树完全一致。

## 源码包文件级比对

已将候选源码包解压到临时目录，并与当前 worktree 的 `jewelry-qms/` 比对，排除项仅限于打包时本来就不包含的本地运行态/依赖目录：

```text
vendor
node_modules
runtime
uploads
.env
.env.*
.DS_Store
```

比对命令无差异输出。

判断：候选源码包与当前 `jewelry-qms/` 内容一致。

## 后续文档提交范围

`780dd2bf..HEAD` 之间的提交只修改了：

```text
.team/交接箱/2026-07-16-G4-R12云端部署准备/
```

未修改 `jewelry-qms/`。

## 对 C1/C2 的影响

- C1：Draft PR 使用当前 HEAD 是合理的，因为它包含完整代码和完整交接记录；
- C2：候选包仍使用 `20260716-g4r12-pr31`，不需要重打包；
- C2 服务器侧发布清单仍作为 release 留档，说明候选包与当前 PR 的源码树一致。
