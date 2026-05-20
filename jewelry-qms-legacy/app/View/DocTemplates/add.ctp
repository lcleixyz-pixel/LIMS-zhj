<h3>上传文件模板</h3>
<p class="text-muted">上传贵实验室现有 Word 模板（.docx），新建体系文件时可选择模板作为起始文档。</p>
<?php echo $this->Form->create('DocTemplate', array('type' => 'file', 'class' => 'form-horizontal')); ?>
    <div class="form-group">
        <label>适用层级 *</label>
        <?php echo $this->Form->select('level', $docLevels, array('class' => 'form-control', 'empty' => '请选择')); ?>
    </div>
    <div class="form-group">
        <label>模板名称 *</label>
        <?php echo $this->Form->input('name', array('label' => false, 'class' => 'form-control', 'div' => false)); ?>
    </div>
    <div class="form-group">
        <label>说明</label>
        <?php echo $this->Form->textarea('description', array('label' => false, 'class' => 'form-control', 'rows' => 3)); ?>
    </div>
    <div class="form-group">
        <label>Word 模板文件 *</label>
        <input type="file" name="template_file" accept=".doc,.docx" class="form-control" required>
    </div>
    <?php echo $this->Form->submit('上传', array('class' => 'btn btn-primary')); ?>
    <?php echo $this->Html->link('返回', array('action' => 'index'), array('class' => 'btn btn-default')); ?>
<?php echo $this->Form->end(); ?>
