@extends('service::layouts.app_install', ['title' => __('service::install.license')])

@section('content')
<style>
#overlay {
  background: url(https://eskooly.com/img/preloader1.gif) center no-repeat #fff;
  width: 100%;
  height: 100%;
  position: absolute;
  top: 0;
  left: 0;
  opacity: .9;
  z-index: 9999;
  color:black;
  
}
#msg,#wrongemail,#msgok,#msg1,#wrongkey,#msgok1,#divCheckPasswordMatch{
		display:none;
		}
</style>
	<div id="overlay" style="display:none;"></div>
    <div class="single-report-admit">
        <div class="card-header">
            <h2 class="text-center text-uppercase color-whitesmoke" >{{ __('service::install.license_verification') }}
            </h2>

        </div>
    </div>

    <div class="card-body">
    <div class="requirements">
        <div class="row">
            <div class="col-md-12">
                <form method="post" action="{{ route('service.license') }}" id="content_form">
                    <div class="form-group">
                        <label class="required" for="access_code">{{ __('service::install.access_code') }}</label>
                        <input type="text" class="form-control " name="access_code" id="access_code" onblur="checkKeyStatus()"  required="required" autofocus=""  placeholder="{{ __('service::install.access_code') }}" >
						<span class="text-danger" id="wrongkey" style="font-size:10px;">Sorry ! This Serial Key is  incorrect or expired.</span>
                    </div>
                    <div class="form-group">
                        <label class="required" for="envato_email">{{ __('service::install.envato_email') }}</label>
                        <input type="email" class="form-control" name="envato_email" id="envato_email" onblur="checkMailStatus()" required="required" placeholder="{{ __('service::install.envato_email') }}" >
						<span class="text-danger" id="wrongemail" style="font-size:10px;">Sorry ! This email Address is not/already Registered with us.</span>
                    </div>

                    <div class="form-group">
                        <label class="required" for="installed_domain">{{ __('service::install.installed_domain') }}</label>
                        <input type="text" class="form-control" name="installed_domain" id="installed_domain" required="required" readonly value="{{ app_url() }}" >
                    </div>
                    @if($reinstall)
                   <div class="form-group">
                        <label data-id="bg_option" class="primary_checkbox d-flex mr-12 ">
                            <input name="re_install" type="checkbox">
                            <span class="checkmark"></span>
                            <span class="ml-2">Re install System</span>
                        </label>
                    </div>
                    @endif

                   <button type="submit" class="offset-3 col-sm-6 primary-btn fix-gr-bg mt-40 submit bc-color" >{{ __('service::install.lets_go_next') }}</button>
                   <button type="button" class="offset-3 col-sm-6 primary-btn fix-gr-bg mt-40 submitting bc-color" disabled style="display:none">{{ __('service::install.submitting') }}</button>
                </form>
            </div>



        </div>
    </div>
</div>
@stop

@push('js')
    <script>
        _formValidation('content_form');
        $(document).ready(function(){
            setTimeout(function(){
                $('.preloader h2').text('We are validating your license. Please do not refresh or close the browser')
            }, 2000);
        })
    </script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
	function checkMailStatus(){
    //alert("came");
	$("#overlay").show();
var reg=$("#envato_email").val();// value in field email
if(reg!=""){
$.ajax({
    type:'post',
        url:'https://eskooly.com/verification/verify.php',// put your real file name 
        data:{email: reg},
        success:function(msg){
			if(msg=="ERROR"){
		  
		  $("#envato_email").focus();
		  $("#wrongemail").slideDown("slow");
		  $("#overlay").hide();
			}else{
				
			$("#wrongemail").slideUp("slow");
			$("#overlay").hide();
			update();
			}
         // alert(msg); // your message will come here.     
        }
 });
}else{
	$("#overlay").hide();
}
}
function checkKeyStatus(){
    //alert("came");
	$("#overlay").show();
var reg1=$("#access_code").val();// value in field email
if(reg1!=""){
$.ajax({
    type:'post',
        url:'https://eskooly.com/verification/verify.php',// put your real file name 
        data:{serialkey: reg1},
        success:function(msg1){
			if(msg1=="ERROR"){
		  
		  
		  $("#wrongkey").slideDown("slow");
		  $("#overlay").hide();
		  $("#access_code").focus();
			}else{
			
			
			$("#wrongkey").slideUp("slow");
			$("#overlay").hide();
			update();
			}
         // alert(msg); // your message will come here.     
        }
 });
}else{
	$("#overlay").hide();
}
}

function update() {
   var val1=$("#envato_email").val();
   var val2=$("#access_code").val();
   if(val1!="" && val2!=""){
 $.ajax({
    type:'post',
        url:'https://eskooly.com/verification/verify.php',// put your real file name 
        data:{remail: val1, rekey:val2},
        success:function(msg){
			if(msg=="ERROR"){
		    location.reload();
			}else{
				
			
			}
         // alert(msg); // your message will come here.     
        }
 });
 }

};
       
    </script>
@endpush
