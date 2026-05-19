<h3>质量管理工作台</h3>
<div class="row">
    <div class="col-md-3"><div class="stat-card"><h2><?php echo $stats['documents_total']; ?></h2><p>体系文件总数</p></div></div>
    <div class="col-md-3"><div class="stat-card"><h2><?php echo $stats['documents_pending']; ?></h2><p>待审批文件</p></div></div>
    <div class="col-md-3"><div class="stat-card"><h2><?php echo $stats['calibration_due']; ?></h2><p>30天内到期校准</p></div></div>
    <div class="col-md-3"><div class="stat-card"><h2><?php echo $stats['capa_open']; ?></h2><p>开放 CAPA</p></div></div>
</div>
<div class="row" style="margin-top:15px">
    <div class="col-md-4"><div class="stat-card"><h2><?php echo $stats['complaints_open']; ?></h2><p>待处理客户投诉</p></div></div>
    <div class="col-md-4"><div class="stat-card"><h2><?php echo $stats['nc_open']; ?></h2><p>待处理不符合工作</p></div></div>
    <div class="col-md-4"><div class="stat-card"><h2><?php echo $stats['audit_findings_open']; ?></h2><p>待关闭审核发现</p></div></div>
</div>

<div class="panel panel-default" style="margin-top:20px">
    <div class="panel-heading"><strong>即将到期校准设备</strong></div>
    <table class="table table-condensed">
        <thead><tr><th>设备编号</th><th>名称</th><th>下次校准日期</th></tr></thead>
        <tbody>
        <?php foreach ($upcomingCalibrations as $eq): ?>
            <tr>
                <td><?php echo h($eq['Equipment']['equipment_number']); ?></td>
                <td><?php echo h($eq['Equipment']['name']); ?></td>
                <td class="text-danger"><?php echo h($eq['Equipment']['next_calibration_date']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($upcomingCalibrations)): ?>
            <tr><td colspan="3" class="text-muted">暂无即将到期记录</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="row" style="margin-top:20px">
    <div class="col-md-6"><?php echo $this->Html->link('文件控制', array('controller' => 'documents', 'action' => 'index'), array('class' => 'btn btn-lg btn-block btn-default')); ?></div>
    <div class="col-md-6"><?php echo $this->Html->link('新建体系文件', array('controller' => 'documents', 'action' => 'add'), array('class' => 'btn btn-lg btn-block btn-primary')); ?></div>
</div>


