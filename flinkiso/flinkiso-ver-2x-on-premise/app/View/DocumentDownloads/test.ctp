<?php if($pdfs){ ?>
	<?php foreach($pdfs as $pdf){?>
		<li class="list-group-item"><a href="<?php echo Router::url('/', true) . 'files/pdf/'. $this->Session->read('User.id'). '/' . $this->request->params['named']['id']  .'/'.$pdf;?>" target="_blank"><?php echo $pdf;?></a></li>
	<?php }?>
<?php }else{ ?>
	<li class="list-group-item">No documents found for this record</li>
<?php } ?>
