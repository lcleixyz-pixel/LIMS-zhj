<div class="page-header">
    <h3><?php echo h($pageTitle); ?>
        <?php echo $this->Html->link('新增', array('action' => 'add'), array('class' => 'btn btn-primary btn-sm pull-right')); ?>
    </h3>
</div>
<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead><tr><th>ID</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php
        $var = $this->params['controller'];
        $key = Inflector::variable(Inflector::pluralize(Inflector::classify($var)));
        if (!isset($$key) && isset($this->request->data)) { $key = null; }
        $items = isset(${$key}) ? ${$key} : array();
        $modelName = Inflector::classify(Inflector::singularize($var));
        foreach ($items as $row):
            $r = $row[$modelName];
        ?>
            <tr>
                <td><?php echo h(substr($r['id'], 0, 8)); ?>...</td>
                <td><?php echo h($r['created']); ?></td>
                <td>
                    <?php echo $this->Html->link('查看', array('action' => 'view', $r['id']), array('class' => 'btn btn-xs btn-info')); ?>
                    <?php echo $this->Html->link('编辑', array('action' => 'edit', $r['id']), array('class' => 'btn btn-xs btn-default')); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="paging">
    <?php echo $this->Paginator->prev('&laquo; 上一页', array('escape' => false, 'tag' => 'li'), null, array('class' => 'disabled', 'tag' => 'li')); ?>
    <?php echo $this->Paginator->numbers(array('separator' => '', 'tag' => 'li', 'currentClass' => 'active')); ?>
    <?php echo $this->Paginator->next('下一页 &raquo;', array('escape' => false, 'tag' => 'li'), null, array('class' => 'disabled', 'tag' => 'li')); ?>
</div>
