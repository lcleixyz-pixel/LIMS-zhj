<div class="page-header">
    <h3>体系文件控制
        <?php echo $this->Html->link('新建文件', array('action' => 'add'), array('class' => 'btn btn-primary btn-sm pull-right')); ?>
    </h3>
</div>

<?php echo $this->Form->create('Document', array('type' => 'get', 'class' => 'form-inline')); ?>
    <div class="form-group">
        <label>层级</label>
        <?php echo $this->Form->select('level', array('' => '全部') + $docLevels, array('empty' => false, 'value' => @$this->request->query['level'], 'class' => 'form-control input-sm')); ?>
    </div>
    <div class="form-group">
        <label>状态</label>
        <?php echo $this->Form->select('status', array('' => '全部', 'draft' => '草稿', 'reviewing' => '审核中', 'published' => '已发布', 'obsolete' => '已废止'), array('value' => @$this->request->query['status'], 'class' => 'form-control input-sm')); ?>
    </div>
    <?php echo $this->Form->submit('筛选', array('class' => 'btn btn-default btn-sm')); ?>
<?php echo $this->Form->end(); ?>

<table class="table table-striped table-bordered" style="margin-top:15px">
    <thead>
        <tr>
            <th>文件编号</th>
            <th>标题</th>
            <th>层级</th>
            <th>版本</th>
            <th>状态</th>
            <th>生效日期</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($documents as $doc): $d = $doc['Document']; ?>
        <tr>
            <td><?php echo h($d['doc_number']); ?></td>
            <td><?php echo h($d['title']); ?></td>
            <td><?php echo h($docLevels[$d['level']]); ?></td>
            <td><?php echo h($d['version']); ?></td>
            <td><span class="status-<?php echo h($d['status']); ?>"><?php echo h($d['status']); ?></span></td>
            <td><?php echo h($d['effective_date']); ?></td>
            <td>
                <?php echo $this->Html->link('查看', array('action' => 'view', $d['id']), array('class' => 'btn btn-xs btn-info')); ?>
                <?php if ($d['status'] !== 'published'): ?>
                    <?php echo $this->Html->link('编辑', array('action' => 'edit', $d['id']), array('class' => 'btn btn-xs btn-default')); ?>
                <?php endif; ?>
                <?php if (!empty($d['file_path'])): ?>
                    <?php echo $this->Html->link('下载', array('action' => 'download', $d['id']), array('class' => 'btn btn-xs btn-success')); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
