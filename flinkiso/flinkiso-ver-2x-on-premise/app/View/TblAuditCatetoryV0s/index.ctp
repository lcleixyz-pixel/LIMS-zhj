<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Catetory', 'pluralHumanName'=>'tbl_audit_catetory_v0s','modelClass'=>'TblAuditCatetoryV0','options'=>array(),'pluralVar'=>'tblAuditCatetoryV0s')));} ?>
<div class="row">
<div class="col-md-12"></div></div>
<?php if($tblAuditCatetoryV0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblAuditCatetoryV0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('name'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblAuditCatetoryV0s as $tblAuditCatetoryV0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblAuditCatetoryV0['TblAuditCatetoryV0']['id'];?>')" id="<?php echo $tblAuditCatetoryV0['TblAuditCatetoryV0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblAuditCatetoryV0['TblAuditCatetoryV0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $tblAuditCatetoryV0['TblAuditCatetoryV0']['name'];?></td>
					<td><?php echo $tblAuditCatetoryV0['PreparedBy']['name'];?></td>
					<td><?php echo $tblAuditCatetoryV0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblAuditCatetoryV0['TblAuditCatetoryV0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblAuditCatetoryV0['TblAuditCatetoryV0']['created_by'], 'postVal' => $tblAuditCatetoryV0['TblAuditCatetoryV0']['id'], 'softDelete' => $tblAuditCatetoryV0['TblAuditCatetoryV0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblAuditCatetoryV0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>