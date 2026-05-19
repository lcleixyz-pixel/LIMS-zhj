<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Customer Complaints', 'pluralHumanName'=>'tbl_customer_complaints_0_v0s','modelClass'=>'TblCustomerComplaints0V0','options'=>array("customer"=>"Customer","complaint_details"=>"Complaint Details","date_received"=>"Date Received","target_date"=>"Target Date","closure_date"=>"Closure Date","assigned_to"=>"Assigned To","current_status"=>"Current Status","resolution_details"=>"Resolution Details","customer_number"=>"Customer Number"),'pluralVar'=>'tblCustomerComplaints0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblCustomerComplaints0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblCustomerComplaints0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('customer','Customer'); ?></th>
					<th><?php echo $this->Paginator->sort('date_received','Date Received'); ?></th>
					<th><?php echo $this->Paginator->sort('target_date','Target Date'); ?></th>
					<th><?php echo $this->Paginator->sort('closure_date','Closure Date'); ?></th>
					<th><?php echo $this->Paginator->sort('assigned_to','Assigned To'); ?></th>
					<th><?php echo $this->Paginator->sort('current_status','Current Status'); ?></th>
					<th><?php echo $this->Paginator->sort('customer_number','Customer Number'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblCustomerComplaints0V0s as $tblCustomerComplaints0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['id'];?>')" id="<?php echo $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $tblCustomerComplaints0V0['Customer'][''];?></td>
					<td><?php 
                                if($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['date_received'])echo  date(Configure::read('dateFormat'),strtotime($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['date_received'])) ;
                                else echo "--";
                                ?></td>
					<td><?php 
                                if($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['target_date'])echo  date(Configure::read('dateFormat'),strtotime($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['target_date'])) ;
                                else echo "--";
                                ?></td>
					<td><?php 
                                if($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['closure_date'])echo  date(Configure::read('dateFormat'),strtotime($tblCustomerComplaints0V0['TblCustomerComplaints0V0']['closure_date'])) ;
                                else echo "--";
                                ?></td>
					<td><?php echo $tblCustomerComplaints0V0['AssignedTo'][''];?></td>
					<td><?php echo $customArray['currentStatuses'][$tblCustomerComplaints0V0['TblCustomerComplaints0V0']['current_status']];?></td>
					<td><?php echo $this->Html->link( $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['customer_number'],array('action'=>'view',$tblCustomerComplaints0V0['TblCustomerComplaints0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php echo $tblCustomerComplaints0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblCustomerComplaints0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['created_by'], 'postVal' => $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['id'], 'softDelete' => $tblCustomerComplaints0V0['TblCustomerComplaints0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblCustomerComplaints0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>