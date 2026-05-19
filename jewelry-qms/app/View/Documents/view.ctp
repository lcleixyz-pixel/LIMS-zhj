<?php $d = $doc['Document']; ?>
<div class="page-header">
    <h3><?php echo h($d['doc_number']); ?> - <?php echo h($d['title']); ?>
        <small class="text-muted">版本 <?php echo h($d['version']); ?></small>
    </h3>
</div>

<div class="row">
    <div class="col-md-8">
        <table class="table table-bordered">
            <tr><th width="120">层级</th><td><?php echo h($docLevels[$d['level']]); ?></td></tr>
            <tr><th>状态</th><td><span class="status-<?php echo h($d['status']); ?>"><?php echo h($d['status']); ?></span></td></tr>
            <tr><th>归口部门</th><td><?php echo h(@$doc['Department']['name']); ?></td></tr>
            <tr><th>生效日期</th><td><?php echo h($d['effective_date']); ?></td></tr>
            <tr><th>复审日期</th><td><?php echo h($d['review_date']); ?></td></tr>
            <?php if ($d['change_reason']): ?>
            <tr><th>变更原因</th><td><?php echo nl2br(h($d['change_reason'])); ?></td></tr>
            <?php endif; ?>
        </table>

        <p>
            <?php if (!empty($d['file_path'])): ?>
                <?php echo $this->Html->link('下载当前版本', array('action' => 'download', $d['id']), array('class' => 'btn btn-success')); ?>
            <?php endif; ?>
            <?php if ($d['status'] === 'draft'): ?>
                <?php echo $this->Html->link('编辑', array('action' => 'edit', $d['id']), array('class' => 'btn btn-default')); ?>
                <?php echo $this->Html->link('提交审核', array('action' => 'submit_review', $d['id']), array('class' => 'btn btn-warning')); ?>
            <?php endif; ?>
            <?php if ($d['status'] === 'published'): ?>
                <?php echo $this->Html->link('发起修订', array('action' => 'revise', $d['id']), array('class' => 'btn btn-primary')); ?>
            <?php endif; ?>
        </p>

        <?php if (!empty($doc['DocumentRevision'])): ?>
        <h4>修订历史</h4>
        <table class="table table-condensed">
            <thead><tr><th>版本</th><th>变更原因</th><th>时间</th></tr></thead>
            <tbody>
            <?php foreach ($doc['DocumentRevision'] as $rev): ?>
                <tr>
                    <td><?php echo h($rev['version']); ?></td>
                    <td><?php echo h($rev['change_reason']); ?></td>
                    <td><?php echo h($rev['created']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading">审批流程</div>
            <ul class="list-group">
            <?php
            $levelNames = array(1 => '编制', 2 => '审核', 3 => '批准');
            foreach ($approvals as $ap):
                $a = $ap['Approval'];
            ?>
                <li class="list-group-item">
                    <strong><?php echo $levelNames[$a['approval_level']]; ?></strong>
                    <span class="label label-<?php echo $a['status'] === 'approved' ? 'success' : ($a['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                        <?php echo h($a['status']); ?>
                    </span>
                    <?php if ($a['status'] === 'pending' && $a['user_id'] === $this->Session->read('User.id')): ?>
                        <?php echo $this->Form->create('Approval', array('url' => array('action' => 'approve', $a['id']))); ?>
                            <?php echo $this->Form->hidden('status', array('value' => 'approved')); ?>
                            <?php echo $this->Form->input('comments', array('label' => false, 'placeholder' => '审批意见', 'class' => 'form-control input-sm')); ?>
                            <button type="submit" class="btn btn-xs btn-success">批准</button>
                        <?php echo $this->Form->end(); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php echo $this->Html->link('返回列表', array('action' => 'index'), array('class' => 'btn btn-default')); ?>
