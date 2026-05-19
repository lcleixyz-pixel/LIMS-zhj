<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Schedule', 'pluralHumanName'=>'tbl_audit_schedule_0_v0s','modelClass'=>'TblAuditSchedule0V0','options'=>array("audit_number"=>"Audit Number","standard"=>"Standard","audit_category"=>"Audit Category","schedule_start_date"=>"Schedule Start Date","scheduled_end_date"=>"Scheduled End Date","audit_locations"=>"Audit Locations","departments_to_be_audited"=>"Departments To Be Audited","current_status"=>"Current Status","notes"=>"Notes"),'pluralVar'=>'tblAuditSchedule0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblAuditSchedule0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblAuditSchedule0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('audit_number','Audit Number'); ?></th>
					<th><?php echo $this->Paginator->sort('current_status','Current Status'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblAuditSchedule0V0s as $tblAuditSchedule0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblAuditSchedule0V0['TblAuditSchedule0V0']['id'];?>')" id="<?php echo $tblAuditSchedule0V0['TblAuditSchedule0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblAuditSchedule0V0['TblAuditSchedule0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $this->Html->link( $tblAuditSchedule0V0['TblAuditSchedule0V0']['audit_number'],array('action'=>'view',$tblAuditSchedule0V0['TblAuditSchedule0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php echo $customArray['currentStatuses'][$tblAuditSchedule0V0['TblAuditSchedule0V0']['current_status']];?></td>
					<td><?php echo $tblAuditSchedule0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblAuditSchedule0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblAuditSchedule0V0['TblAuditSchedule0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblAuditSchedule0V0['TblAuditSchedule0V0']['created_by'], 'postVal' => $tblAuditSchedule0V0['TblAuditSchedule0V0']['id'], 'softDelete' => $tblAuditSchedule0V0['TblAuditSchedule0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblAuditSchedule0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>