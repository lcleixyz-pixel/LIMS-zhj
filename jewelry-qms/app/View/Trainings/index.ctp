<div class="page-header">
    <h3><?php echo h($pageTitle); ?>
        <?php echo $this->Html->link('鏂板', array('action' => 'add'), array('class' => 'btn btn-primary btn-sm pull-right')); ?>
    </h3>
</div>
<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead><tr><th>ID</th><th>鍒涘缓鏃堕棿</th><th>鎿嶄綔</th></tr></thead>
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
                    <?php echo $this->Html->link('鏌ョ湅', array('action' => 'view', $r['id']), array('class' => 'btn btn-xs btn-info')); ?>
                    <?php echo $this->Html->link('缂栬緫', array('action' => 'edit', $r['id']), array('class' => 'btn btn-xs btn-default')); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $this->element('pagination'); ?>


