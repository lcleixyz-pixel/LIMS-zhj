<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'MRM', 'pluralHumanName'=>'tbl_mrm_0_v0s','modelClass'=>'TblMrm0V0','options'=>array("meeting_number"=>"Meeting Number","scheduled_date_time"=>"Scheduled Date Time","proposed_by"=>"Proposed By","meeting_details"=>"Meeting Details","invitees"=>"Invitees","meeting_status"=>"Meeting Status","comments5"=>"Comments5","actual_meeting_date_time"=>"Actual Meeting Date Time","attainted_by"=>"Attainted By"),'pluralVar'=>'tblMrm0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblMrm0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblMrm0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('meeting_number','Meeting Number'); ?></th>
					<th><?php echo $this->Paginator->sort('proposed_by','Proposed By'); ?></th>
					<th><?php echo $this->Paginator->sort('meeting_status','Meeting Status'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblMrm0V0s as $tblMrm0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblMrm0V0['TblMrm0V0']['id'];?>')" id="<?php echo $tblMrm0V0['TblMrm0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblMrm0V0['TblMrm0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $this->Html->link( $tblMrm0V0['TblMrm0V0']['meeting_number'],array('action'=>'view',$tblMrm0V0['TblMrm0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php echo $tblMrm0V0['ProposedBy']['name'];?></td>
					<td><?php echo $customArray['meetingStatuses'][$tblMrm0V0['TblMrm0V0']['meeting_status']];?></td>
					<td><?php echo $tblMrm0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblMrm0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblMrm0V0['TblMrm0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblMrm0V0['TblMrm0V0']['created_by'], 'postVal' => $tblMrm0V0['TblMrm0V0']['id'], 'softDelete' => $tblMrm0V0['TblMrm0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblMrm0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>