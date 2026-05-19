<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Checklist', 'pluralHumanName'=>'tbl_audit_checklist_0_v0s','modelClass'=>'TblAuditChecklist0V0','options'=>array("checklist_title"=>"Checklist Title","date_added"=>"Date Added","added_by"=>"Added By","comments"=>"Comments"),'pluralVar'=>'tblAuditChecklist0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblAuditChecklist0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblAuditChecklist0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('checklist_title','Checklist Title'); ?></th>
					<th><?php echo $this->Paginator->sort('date_added','Date Added'); ?></th>
					<th><?php echo $this->Paginator->sort('added_by','Added By'); ?></th>
					<th><?php echo $this->Paginator->sort('comments','Comments'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblAuditChecklist0V0s as $tblAuditChecklist0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblAuditChecklist0V0['TblAuditChecklist0V0']['id'];?>')" id="<?php echo $tblAuditChecklist0V0['TblAuditChecklist0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblAuditChecklist0V0['TblAuditChecklist0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $this->Html->link( $tblAuditChecklist0V0['TblAuditChecklist0V0']['checklist_title'],array('action'=>'view',$tblAuditChecklist0V0['TblAuditChecklist0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php 
                                if($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])echo  date(Configure::read('dateFormat'),strtotime($tblAuditChecklist0V0['TblAuditChecklist0V0']['date_added'])) ;
                                else echo "--";
                                ?></td>
					<td><?php echo $tblAuditChecklist0V0['AddedBy']['name'];?></td>
					<td><?php echo $tblAuditChecklist0V0['TblAuditChecklist0V0']['comments'];?></td>
					<td><?php echo $tblAuditChecklist0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblAuditChecklist0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblAuditChecklist0V0['TblAuditChecklist0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblAuditChecklist0V0['TblAuditChecklist0V0']['created_by'], 'postVal' => $tblAuditChecklist0V0['TblAuditChecklist0V0']['id'], 'softDelete' => $tblAuditChecklist0V0['TblAuditChecklist0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblAuditChecklist0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>