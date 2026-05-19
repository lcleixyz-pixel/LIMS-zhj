<div  id="main">
<?php echo $this->Session->flash();?>
<?php echo $this->element('nav-header-lists',array('postData'=>array('friendlyName'=>'Customer Details','pluralHumanName'=>'tbl_customer_details_0_v0s','modelClass'=>'TblCustomerDetails0V0','options'=>array("customer_name"=>"Customer Name","customer_details"=>"Customer Details","official_email"=>"Official Email","phone"=>"Phone","fax"=>"Fax","head_office_address"=>"Head Office Address","customer_status"=>"Customer Status","customer_type"=>"Customer Type","customer_name"=>"Customer Name","customer_details"=>"Customer Details","official_email"=>"Official Email","phone"=>"Phone","fax"=>"Fax","head_office_address"=>"Head Office Address","customer_status"=>"Customer Status","customer_type"=>"Customer Type"),'pluralVar'=>'tblCustomerDetails0V0s'))); ?>
<div id="load_parent">
    <?php if($tblCustomerDetails0V0['TblCustomerDetails0V0']['parent_id'] && isset($parent_table_name)){ ?>
    <script type="text/javascript">
        $().ready(function(){
            $.ajax({
                url: "<?php echo Router::url('/', true); ?><?php echo $parent_table_name;?>/view/<?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['parent_id'];?>",
                type : "POST",
                data : {                                                            
                    'Access[skip_access_check]': 1,                             
                    'Access[allow_access_user]': '<?php echo $this->Session->read('User.id');?>',
                    },
                success: function(data, result) {                               
                    $("#load_parent").html($(data).find("\#ajax_view").html());
                },                              
            });
        });
    </script>
    <?php }?>
</div> 
<div id="ajax_view">
<div class="row"><div class="col-md-12">
<?php echo $this->element('qc_doc_header',array('document',$document));?></div></div>
<div class="view-record"><h2><small>Customer Name: </small><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_name'];?></h2>

<div class="row">
    <div class="col-md-12">
        <strong style="float:left"><?php echo $document['QcDocument']['name'];?> Document</strong>
        <?php       
            $key = $fileEdit['File']['file_key'];
            $file_type = $fileEdit['File']['file_type'];
            $file_name = $fileEdit['File']['name'];
            
            if($file_type == 'doc' || $file_type == 'docx'){
                $documentType = 'word';
            }

            if($file_type == 'xls' || $file_type == 'xlsx'){
                $documentType = 'cell';
            }
            $mode = 'view';
            $file_path = $fileEdit['File']['id'];
            $file_name = $this->requestAction(array('action'=>'clean_table_names',$file_name));
            $file = $file_name.'.'.$file_type;
            
            echo $this->element('onlyoffice',array(
                'url'=>$url,                       
                'user_id'=>$user_id,               
                'placeholderid'=>$placeholderid,
                'panel_title'=>'Document Viewer',
                'mode'=>$mode,                     
                'path'=>$file_path,                
                'file'=>$file,
                'filetype'=>$file_type,
                'documentType'=>$documentType,
                'userid'=>$this->Session->read('User.id'),
                'username'=>$this->Session->read('User.username'),
                'preparedby'=>$this->Session->read('User.name'),
                'filekey'=>$key,            
                'record_id'=>$fileEdit['File']['id'],
                'company_id'=>$this->Session->read('User.company_id'),
                'controller'=>$this->request->controller,
                'last_saved' => $fileEdit['File']['last_saved'],
                'last_modified' => $fileEdit['File']['modified'],
                'version_keys' => $fileEdit['File']['version_keys'],
                'version' => $fileEdit['File']['version'],
                'versions' => $fileEdit['File']['versions'],
                'docid'=> $qcDocument['QcDocument']['id']
            ));
        ?>
    </div>
</div>
<br />

	<div class="table-responsive">
		<table class="table table-bordered table-responsive" id="exportcsv"  >
			<tr>
				<th width="30%"> Customer Name</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_name'];?></td>
			</tr>
			<tr>
				<th width="30%"> Customer Details</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_details'];?></td>
			</tr>
			<tr>
				<th width="30%"> Official Email</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['official_email'];?></td>
			</tr>
			<tr>
				<th width="30%"> Phone</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['phone'];?></td>
			</tr>
			<tr>
				<th width="30%"> Fax</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['fax'];?></td>
			</tr>
			<tr>
				<th width="30%"> Head Office Address</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['head_office_address'];?></td>
			</tr>
			<tr>
				<th width="30%">Customer Status</th>
	<td><?php echo $customArray['customerStatuses'][$tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_status']];?></td>
			</tr>
			<tr>
				<th width="30%">Customer Type</th>
	<td><?php echo $customArray['customerTypes'][$tblCustomerDetails0V0['TblCustomerDetails0V0']['customer_type']];?></td>
			</tr>
			<tr>
				<th width="30%">Prepared By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblCustomerDetails0V0['PreparedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblCustomerDetails0V0['PreparedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblCustomerDetails0V0['PreparedBy']['signature']){
                            
				echo '<img src="'.$tblCustomerDetails0V0['PreparedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblCustomerDetails0V0['PreparedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Approved By</th>
				<td><?php 
				$img = WWW_ROOT. DS. 'img'. DS . $this->Session->read('User.company_id'). DS .'signature'. DS. $tblCustomerDetails0V0['ApprovedBy']["id"] . DS. 'sign.png';

                        
				if(file_exists($img)){
                            
				$imgUrl = Router::url('/', true).'/img/' . $this->Session->read('User.company_id') .'/signature/'. $tblCustomerDetails0V0['ApprovedBy']["id"] . '/sign.png';
                            
				echo '<img src="'.$imgUrl.'" width="100"><br />';
                        
				}else if($tblCustomerDetails0V0['ApprovedBy']['signature']){
                            
				echo '<img src="'.$tblCustomerDetails0V0['ApprovedBy']['signature'].'" width="100"><br />';
                        }else{ echo '<small style="color:#810000">Signature not found</small><br />';} 
				?><?php echo $tblCustomerDetails0V0['ApprovedBy']['name'];?></td>
			</tr>
			<tr>
				<th width="30%">Published?</th>
                                <td><?php echo $tblCustomerDetails0V0['TblCustomerDetails0V0']['publish']?'Yes':'No';?></td>
			</tr>
		</table>
        </div>
    </div>
</div>

<?php if($loadLinkedTables){ ?>
<div class="row">
    <div class="col-md-12">     
        <div id="linked_documents">         
        <?php   
            echo "<ul>";
            foreach($loadLinkedTables as $loadLinkedTable){ 
                echo "<li>" . $this->Html->link(Inflector::humanize($loadLinkedTable['name']),array(
                    'controller'=> $loadLinkedTable['table_name'],
                    'action'=>'index',
                    'custom_table_id'=>$loadLinkedTable['custom_table_id'],
                    'parent_record_id'=>$this->request->params['pass'][0],
                    'qc_document_id'=> $loadLinkedTable['qc_document_id']

                )) . "</li>";
            } 
            echo "</ul>"; ?>                
        </div>  
    </div>
</div>
<?php } ?>
<script type="text/javascript">
    $( function() {
        $( "#linked_documents" ).tabs({
          beforeLoad: function( event, ui ) {
            ui.jqXHR.fail(function() {
              ui.panel.html(
                "Couldn't load this tab. We'll try to fix this as soon as possible. " +
                "If this wouldn't be a demo." );
            });
          }
        });
      } );
</script>

<div class="row">
    <div class="col-md-12">
        <?php 
           foreach($linkedTables as $linkedTable){?>
        <div><h4>Linked Table : <?php echo Inflector::humanize($linkedTable['CustomTable']['name']);?></h4></div>
        <div id="<?php echo $linkedTable['CustomTable']['table_name']?>"></div>
        <script type="text/javascript">
            $("#<?php echo $linkedTable['CustomTable']['table_name']?>").load("<?php echo Router::url('/', true); ?><?php echo $linkedTable['CustomTable']['table_name']?>/index/<?php echo $this->request->params['pass'][0];?>/custom_table_id:<?php echo $linkedTable['CustomTable']['id'];?>");
        </script>
    <?php } ?>
    </div>
</div>
<?php if($loadLinkedTables){ ?>
<div class="row">
    <div class="col-md-12">     
        <div id="linked_documents_add">         
        <?php   
            echo "<ul>";
            foreach($loadLinkedTables as $loadLinkedTable){ 
                if($loadLinkedTable['action'] != 'index'){
                    echo "<li>" . $this->Html->link(Inflector::humanize($loadLinkedTable['name']),array(
                        'controller'=> $loadLinkedTable['table_name'],
                        'action'=>$loadLinkedTable['action'],
                        'custom_table_id'=>$loadLinkedTable['custom_table_id'],
                        'parent_record_id'=>$this->request->params['pass'][0],
                        'qc_document_id'=> $loadLinkedTable['qc_document_id'],
                    )) . "</li>";
                }               
            } 
            echo "</ul>"; ?>                
        </div>  
    </div>
</div>
<?php } ?>
<script type="text/javascript">
    $( function() {
        $( "#linked_documents_add" ).tabs({
          beforeLoad: function( event, ui ) {
            ui.jqXHR.fail(function() {
              ui.panel.html(
                "Couldn't load this tab. We'll try to fix this as soon as possible. " +
                "If this wouldn't be a demo." );
            });
          }
        });
      } );
</script>


<div class="row">
    <div class="col-md-12">
        <?php echo $this->element('approval_history',array('approval'=>$approval,'approvals'=>$approvals,'current_approval'=>$this->request->params['named']['approval_id'],'approvalComments',$approvalComments));?>
    </div>
    <div class="col-md-12">
        <?php echo $this->element('approval_form',array('approval'=>$approval));?>        
    </div>
</div>