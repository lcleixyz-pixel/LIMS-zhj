<div  id="main" class="custom">
<?php echo $this->Session->flash();?>

<?php if(!$this->request->params['named']['parent_record_id']){ echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Customer Details', 'pluralHumanName'=>'tbl_customer_details_0_v0s','modelClass'=>'TblCustomerDetails0V0','options'=>array("customer_name"=>"Customer Name","customer_details"=>"Customer Details","official_email"=>"Official Email","phone"=>"Phone","fax"=>"Fax","head_office_address"=>"Head Office Address","customer_status"=>"Customer Status","customer_type"=>"Customer Type"),'pluralVar'=>'tblCustomerDetails0V0s')));} ?>
<div class="row">
<div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<?php if($tblCustomerDetails0V0s){ ?>
<div class="panel panel-default"><div class="panel-body no-padding">
	<div class="table-responsive" style="overflow:scroll">
		<?php echo $this->Form->create('TblCustomerDetails0V0',array('class'=>'no-padding no-margin no-background'));?>
			<table class="table table-hover table-responsive index" id="exportcsv">
				<tr>
					<th><?php echo $this->Paginator->sort('customer_name','Customer Name'); ?></th>
					<th><?php echo $this->Paginator->sort('prepared_by'); ?></th>
					<th><?php echo $this->Paginator->sort('approved_by'); ?></th>
					<th><?php echo $this->Paginator->sort('publish'); ?></th>
					<th>Actions</th>
				</tr>
			<?php foreach ($tblCustomerDetails0V0s as $tblCustomerDetails0V0): ?><?php if(!$this->request->params['named']['parent_record_id']){ ?>
            
				<tr class="on_page_src" onclick="addrec('<?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['id'];?>')" id="<?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['id'];?>_tr">
            <?php }else{ ?>
                
				<tr class="on_page_src" id="<?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['id'];?>_tr">
            <?php };?>
					<td><?php echo $this->Html->link( $tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_name'],array('action'=>'view',$tblCustomerDetails0V0['TblCustomerDetails0V0']['id'],'qc_document_id'=>$this->request->params['named']['qc_document_id'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],'process_id'=>$this->request->params['named']['process_id']));?></td>
					<td><?php echo $tblCustomerDetails0V0['PreparedBy']['name'];?></td>
					<td><?php echo $tblCustomerDetails0V0['ApprovedBy']['name'];?></td>
					<td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['publish']?'Yes':'No';?></td>
					<td class=" actions">	
					<?php echo $this->element('actions', array('created' => $tblCustomerDetails0V0['TblCustomerDetails0V0']['created_by'], 'postVal' => $tblCustomerDetails0V0['TblCustomerDetails0V0']['id'], 'softDelete' => $tblCustomerDetails0V0['TblCustomerDetails0V0']['soft_delete'],'custom_table_id'=>$this->request->params['named']['custom_table_id'],  'qc_document_id'=>$this->request->params['named']['qc_document_id'],'process_id'=>$this->request->params['named']['process_id'])); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php echo $this->Form->end();?>
<?php } ?>
<?php if(!$tblCustomerDetails0V0s){ ?>
	<p class='text-center'><br /><span class='text-danger'><i class='fa fa-exclamation-triangle';></i> No records found.</span></p>
<?php } ?>
	</div>
<?php if(!$this->request->params['named']['parent_record_id']){echo $this->element('paging');}?>
</div></div>
</div></div>