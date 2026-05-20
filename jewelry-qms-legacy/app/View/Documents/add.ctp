<h3>新建体系文件</h3>
<?php echo $this->Form->create('Document', array('type' => 'file', 'class' => 'form-horizontal')); ?>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>文件层级 *</label>
            <?php echo $this->Form->select('level', $docLevels, array('class' => 'form-control', 'empty' => '请选择', 'id' => 'doc-level')); ?>
            <p class="help-block">质量手册/程序文件：三级审批；SOP/记录：两级审批</p>
        </div>
        <div class="form-group">
            <label>文件编号 *</label>
            <?php echo $this->Form->input('doc_number', array('label' => false, 'class' => 'form-control', 'div' => false, 'placeholder' => '如 QM-01、QP-04-01')); ?>
        </div>
        <div class="form-group">
            <label>文件名称 *</label>
            <?php echo $this->Form->input('title', array('label' => false, 'class' => 'form-control', 'div' => false)); ?>
        </div>
        <div class="form-group">
            <label>分类</label>
            <?php echo $this->Form->select('category_id', $categories, array('class' => 'form-control', 'empty' => '请选择')); ?>
        </div>
        <div class="form-group">
            <label>归口部门</label>
            <?php echo $this->Form->select('department_id', $departments, array('class' => 'form-control', 'empty' => '请选择')); ?>
        </div>
        <div class="form-group">
            <label>关联模板</label>
            <?php echo $this->Form->select('template_id', $templates, array('class' => 'form-control', 'empty' => '无（上传自有文件）')); ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>版本号</label>
            <?php echo $this->Form->input('version', array('label' => false, 'class' => 'form-control', 'div' => false, 'default' => 'A/0')); ?>
        </div>
        <div class="form-group">
            <label>生效日期</label>
            <?php echo $this->Form->input('effective_date', array('type' => 'date', 'label' => false, 'class' => 'form-control', 'div' => false)); ?>
        </div>
        <div class="form-group">
            <label>审核人（员工）</label>
            <?php echo $this->Form->select('reviewed_by', $employees, array('class' => 'form-control', 'empty' => '请选择', 'id' => 'reviewed-by')); ?>
        </div>
        <div class="form-group">
            <label>批准人（员工）</label>
            <?php echo $this->Form->select('approved_by', $employees, array('class' => 'form-control', 'empty' => '请选择', 'id' => 'approved-by')); ?>
        </div>
        <div class="form-group">
            <label>上传 Word 文件 (.doc/.docx) *</label>
            <input type="file" name="document_file" class="form-control" accept=".doc,.docx,.pdf" required>
        </div>
    </div>
</div>

<?php echo $this->Form->submit('保存并提交审批', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Html->link('返回', array('action' => 'index'), array('class' => 'btn btn-default')); ?>
<?php echo $this->Form->end(); ?>

<script>
$('#doc-level').change(function() {
    var l = parseInt($(this).val(), 10);
    if (l === 3 || l === 4) {
        $('#reviewed-by').closest('.form-group').hide();
    } else {
        $('#reviewed-by').closest('.form-group').show();
    }
});
</script>
