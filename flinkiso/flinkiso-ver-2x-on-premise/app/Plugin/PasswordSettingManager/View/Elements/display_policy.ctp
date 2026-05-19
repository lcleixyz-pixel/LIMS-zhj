<?php 
if($password_setting['activate_password_setting'] == 0){
    echo "<div style='margin-left:-15px' class='alert alert-warning'>Password Policy is not active.</div>";
    echo "<div>You can define and active the Password Policy from application's Setting menu.</div>";
}else{ ?>
<?php if($password_setting['activate_password_setting'] && $password_setting['display_policy']){?>
    <div class="row">
        <div class="col-md-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading"><div class="panel-title"><?php echo __('Password Policy')?></div>
            </div>
            <div class="panel-body">
                <ul>
                    <?php if($password_setting['password_same_username'] > 0){?>
                         <h5><li>Password should not be same as username </li> </h5>
                    <?php } if($password_setting['password_min_len']){ ?>
                         <h5><li>Password should be atleast <?php echo $password_setting['password_min_len'];?> character long </li> </h5>
                    <?php } if($password_setting['password_max_len']){ ?>
                        <h5><li>Password should be maximum <?php echo $password_setting['password_max_len'];?> character long  </li> </h5>
                    <?php } if($password_setting['password_uppercase_length']){ ?>
                        <h5><li>Password should contain at least <?php echo $password_setting['password_uppercase_length']; ?> number of UPPERCASE characters </li> </h5>
                    <?php } if($password_setting['password_uppercase_start'] == 1){ ?>
                        <h5><li>Password must start with UPPERCASE character</li></h5>
                    <?php }else if($password_setting['password_uppercase_start'] == 2){?>
                        <h5><li>Password must not start with UPPERCASE character</li></h5>
                    <?php } ?>         
                    <?php if($password_setting['password_special_character'] == 1){ ?>
                        <h5><li>Password must contain special characters</li></h5>
                    <?php }else if($password_setting['password_special_character'] == 2){?>
                        <h5><li>Password must not contain special characters</li></h5>
                    <?php } ?>         
                        
                          <?php if($password_setting['password_repeat']){ ?>
                        <h5><li> Last  <?php echo $password_setting['password_repeat']; ?> passwords can not be repeated  </li> </h5>
                        <?php } ?>       
                </ul>
            </div>
        </div>
    </div>
</div>
<script> jQuery.validator.addMethod("passwordPolicy", function(value, element) {
             
<?php /*if($password_setting['password_same_username'] == 1){
    if(isset($username)){?>
        var username = '<?php echo $username; ?>';
        
   <?php }else{
    ?>
    
    var username = $( "input[name*='username']" ).val();  
     <?php } ?>
 
    if(value == username){
        $.validator.messages.passwordPolicy = "Password should not be same as username";
        return false;
    }
<?php } */

if($password_setting['password_min_len']){ ?>
     var minlen = '<?php echo $password_setting['password_min_len']; ?>';
   
        if(value.length < minlen){
           $.validator.messages.passwordPolicy = 'Password should be atleast '+minlen+' character long ';
           return false;
       }
<?php  } if($password_setting['password_max_len']){ ?>
     var maxlen = '<?php echo $password_setting['password_max_len']; ?>';
   
        if(value.length > maxlen){
           $.validator.messages.passwordPolicy = 'Password should be maximum '+maxlen+' character long ';
           return false;
       }
<?php  } if($password_setting['password_special_character'] == 1){ ?>
        var regex = new RegExp("^[a-zA-Z0-9.]*$"); 
        if(regex.test(value)){
            $.validator.messages.passwordPolicy = 'Password must contain special characters';
            return false;
        }
<?php }else if($password_setting['password_special_character'] == 2){?>
    
     var regex = new RegExp("^[a-zA-Z0-9.]*$"); 
        if(!regex.test(value)){
            $.validator.messages.passwordPolicy = 'Password should not contain any special characters';
            return false;
        }
<?php } if($password_setting['password_uppercase_start'] == 1){ ?>
        var regex = new RegExp("^[A-Z]*$"); 
        if(!regex.test(value.charAt(0))){
        //if(value.charAt(0) != value.charAt(0).toUpperCase()){
            $.validator.messages.passwordPolicy = 'Password must start with UPPERCASE character';
            return false;
        }
<?php } else if($password_setting['password_uppercase_start'] == 2){ ?>
        //if(value.charAt(0) == value.charAt(0).toUpperCase()){
        var regex = new RegExp("^[A-Z]*$"); 
        if(regex.test(value.charAt(0))){
            $.validator.messages.passwordPolicy = 'Password should not start with UPPERCASE character';
            return false;
        }
        
<?php } if($password_setting['password_uppercase_length']){ ?>
        
        var regex = new RegExp("^[A-Z]*$");
        var count = 0;
        for(var i = 0; i < value.length; i++)
        {
              // if(value.charAt(i) == value.charAt(i).toUpperCase())
                if(regex.test(value.charAt(i)))
                {
                      count= count+1;

                }
        }
     
        var minUpperChar = <?php echo $password_setting['password_uppercase_length']; ?>;
        
        if(count< minUpperChar){
            $.validator.messages.passwordPolicy = 'Password should contain at least '+ minUpperChar+' UPPERCASE characters';
            return false;
        }
<?php } ?> 
    $.validator.messages.passwordPolicy = '';
    return true;
    }, "Please select the value");
</script>                
    <?php }   ?>
<?php } ?>
