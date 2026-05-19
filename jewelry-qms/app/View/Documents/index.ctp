<div class="page-header">
    <h3>浣撶郴鏂囦欢鎺у埗
        <?php echo $this->Html->link('鏂板缓鏂囦欢', array('action' => 'add'), array('class' => 'btn btn-primary btn-sm pull-right')); ?>
    </h3>
</div>

<?php echo $this->Form->create('Document', array('type' => 'get', 'class' => 'form-inline')); ?>
    <div class="form-group">
        <label>灞傜骇</label>
        <?php echo $this->Form->select('level', array('' => '鍏ㄩ儴') + $docLevels, array('empty' => false, 'value' => @$this->request->query['level'], 'class' => 'form-control input-sm')); ?>
    </div>
    <div class="form-group">
        <label>鐘舵€?/label>
        <?php echo $this->Form->select('status', array('' => '鍏ㄩ儴', 'draft' => '鑽夌', 'reviewing' => '瀹℃牳涓?, 'published' => '宸插彂甯?, 'obsolete' => '宸插簾姝?), array('value' => @$this->request->query['status'], 'class' => 'form-control input-sm')); ?>
    </div>
    <?php echo $this->Form->submit('绛涢€?, array('class' => 'btn btn-default btn-sm')); ?>
<?php echo $this->Form->end(); ?>

<table class="table table-striped table-bordered" style="margin-top:15px">
    <thead>
        <tr>
            <th>鏂囦欢缂栧彿</th>
            <th>鏍囬</th>
            <th>灞傜骇</th>
            <th>鐗堟湰</th>
            <th>鐘舵€?/th>
            <th>鐢熸晥鏃ユ湡</th>
            <th>鎿嶄綔</th>
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
                <?php echo $this->Html->link('鏌ョ湅', array('action' => 'view', $d['id']), array('class' => 'btn btn-xs btn-info')); ?>
                <?php if ($d['status'] !== 'published'): ?>
                    <?php echo $this->Html->link('缂栬緫', array('action' => 'edit', $d['id']), array('class' => 'btn btn-xs btn-default')); ?>
                <?php endif; ?>
                <?php if (!empty($d['file_path'])): ?>
                    <?php echo $this->Html->link('涓嬭浇', array('action' => 'download', $d['id']), array('class' => 'btn btn-xs btn-success')); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>


