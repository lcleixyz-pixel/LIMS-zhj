<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Audit Findings', 'pluralHumanName'=>'tbl_audit_findings_0_v0s','modelClass'=>'TblAuditFindings0V0','options'=>array("finding_number"=>"Finding Number","audit_start_date"=>"Audit Start Date","audit_end_date"=>"Audit End Date","auditor"=>"Auditor","auditee"=>"Auditee","finding_type"=>"Finding Type","current_status"=>"Current Status","findings"=>"Findings","response_from_auditee"=>"Response From Auditee"),'pluralVar'=>'tblAuditFindings0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblAuditFindings0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblAuditFindings0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('finding_number','Finding Number'); ?></th>
					<th><?php echo $this->Paginator->sort('auditor','Auditor'); ?></th>
					<th><?php echo $this->Paginator->sort('auditee','Auditee'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblAuditFindings0V0s as $tblAuditFindings0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['id'];?>')" id="<?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $this->Html->link( $tblAuditFindings0V0['TblAuditFindings0V0']['finding_number'],array('action'=>'view',$tblAuditFindings0V0['TblAuditFindings0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php echo $tblAuditFindings0V0['Auditor']['name'];?></td>
					<td><?php echo $tblAuditFindings0V0['Auditee']['name'];?></td>
					<td><?php echo $tblAuditFindings0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblAuditFindings0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblAuditFindings0V0['TblAuditFindings0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblAuditFindings0V0['TblAuditFindings0V0']['created_by'], 'postVal' => $tblAuditFindings0V0['TblAuditFindings0V0']['id'], 'softDelete' => $tblAuditFindings0V0['TblAuditFindings0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblAuditFindings0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>