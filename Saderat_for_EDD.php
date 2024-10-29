<?php
/**
	Plugin Name: Bank Saderat Iran EDD gateway
	Version: 1.1
	Description:  این افزونه درگاه بانک صادرات و شبکه پرداخت الکترونیک شاپرک را به افزونه فروش فایل EDD اضافه می‌کند.
	Plugin URI: http://ham3da.ir/saderat-iran-edd-gateway
	Author: Javad Ahshamian
	Author URI: http://www.ham3da.ir/
	License: GPLv2
	Tested up to: 4.7.1
**/

include "menu_setup.php";


if (!defined('ABSPATH') ) {
	echo "HAM3DA";
	exit;
}

if(class_exists("nusoap_base")== false)
{
    require_once('lib/nusoap.php');
}

@session_start();
/////---------------------------------------------------
function edd_bps_rial ($formatted, $currency, $price) {

	return $price . ' ریال';
}
add_filter( 'edd_rial_currency_filter_after', 'edd_bps_rial', 10, 3 );
/////------------------------------------------------
function bps_add_gateway ($gateways) {
	$gateways['Saderat'] = array('admin_label' => 'درگاه بانک صادرات ایران', 'checkout_label' => 'بانک صادرات ایران');
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'bps_add_gateway' );

function bps_cc_form() {
	do_action( 'bps_cc_form_action' );
}
add_filter( 'edd_Saderat_cc_form', 'bps_cc_form' );

/////--------------------------------------------------
function bpsRequest(&$BpsWs, $TermID, $ResNUM, $TotalAmount) 
{

    $res =  $BpsWs->RequestToken($TermID, $ResNUM, $TotalAmount);
    if( $res == '')
    {
    	$res = "-2";
    }
	return $res;
}

function bsi_insert_in_payment()
{
    
    if(!defined('DB_NAME_Pay'))
    {
        require_once(ABSPATH . 'wp-config.php');
    }
    
    $payment_data = $_SESSION['bsi_payment_data'];
    
    $date1 = jdate("Y-m-d",time());
    $postid = get_the_ID();

    $mydb = new wpdb(DB_USER_Pay, DB_PASS_Pay, DB_NAME_Pay, 'localhost');

    $tablename = 'my_pay2';
    
    $cart_details = $payment_data['cart_details'];

    $user_info = $payment_data['user_info'];
    
    $count_cart = count($cart_details);
    
    $pro_name = "";
    
    for($i=0; $i<= $count_cart-1; $i++)
    {
            $pro_name= $cart_details[$i]["name"];
            $price_cart = $cart_details[$i]["price"];
            $cart_id = $cart_details[$i]["id"];

            $sql = $mydb->prepare("INSERT INTO my_pay2(Name, Software, Email, Date, Pay_State, Amount, au, Soft_id, bank) 
            values (%s, %s, %s, %s, %d, %f, %d, %d, %s)", 
            $user_info['first_name'].' '.$user_info['last_name'],
            $pro_name,
            $user_info['email'],
            $date1,
            1,
            $price_cart,
            $_SESSION['bsi_TraceNo'],
            $cart_id,
            'صادرات ایران'
            );
            
            $mydb->query($sql);
        
    }

}


/////-------------------------------------------------
function bps_process_payment($purchase_data) 
{
	error_reporting(0);
	global $edd_options;
    
	$bps_ws = 'https://sep.shaparak.ir/Payments/InitPayment.asmx?WSDL';

	$i=0;
	do 
    {
		$BpsWs = new nusoap_client($bps_ws,'wsdl');
        $soapProxy = $BpsWs->getProxy();
		$i++;
	} 
    while($BpsWs->getError() and $i<3);
    
    
	// Check for Connection error
	if ($BpsWs->getError())
    {
		edd_set_error( 'pay_00', 'P00:خطایی در اتصال پیش آمد،مجدد تلاش کنید...' );
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending',
	)
    ;
	$payment = edd_insert_payment($payment_data);
    
    
    
    $post_url = "https://sep.shaparak.ir/Payment.aspx";
    
	$terminalId = $edd_options['Saderat_TermID'];
	$userName = $edd_options['Saderat_UserName'];
	$userPassword = $edd_options['Saderat_PassWord'];
    
	if ($payment) 
    {
        $_SESSION['bsi_payment_data'] = $payment_data;
        
		$_SESSION['Saderat_payment'] = $payment;
		$return = add_query_arg('order', 'Saderat', get_permalink($edd_options['success_page']));
		$orderId = date('ym').date('His').$payment;
		$amount = $purchase_data['price'];
		$localDate = date("Ymd");
		$localTime = date("His");
		$additionalData = "Purchase key: ".$purchase_data['purchase_key'];
		$payerId = 0;

/////////////////PAY REQUEST PART/////////////////////////
		// Call the SOAP method
		$i=0;
		do
        {
			$PayResult = bpsRequest($soapProxy, $terminalId, $orderId, $amount);
			$i++;
		}
        while($PayResult == "-2" and $i<3);
        
///************END of PAY REQUEST***************///
		if ($PayResult != "-2") 
        {
			// Successfull Pay Request
            
			echo '
                <form id="SaderatPay" name="SaderatPay" method="post" action="'. $post_url .'">
                <input type="hidden" name="Token" value="'.$PayResult.'">
                <input type="hidden" name="RedirectURL" value="'. $return .'">
                </form> 
                <script type="text/javascript">
                      function setAction(element_id)   
                                { 
                                   var frm = document.getElementById(element_id);
                                   if(frm)
                                   {
                                   frm.action = '."'https://sep.shaparak.ir/Payment.aspx'".';  
                                   }
                                } 
                                setAction('."'SaderatPay'".');
                 </script>       
				<script type="text/javascript">document.SaderatPay.submit();</script>
                
			';
			exit();
  		}
        else
        {
			edd_update_payment_status($payment, 'failed');
			edd_insert_payment_note( $payment, 'P02:'.bps_CheckStatus((int)$PayResult) );
			edd_set_error( 'pay_02', ':P02'.bps_CheckStatus((int)$PayResult) );
			edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
		}
	}
    else
    {
		edd_set_error( 'pay_01', 'P01:خطا در ایجاد پرداخت، لطفاً مجدداً تلاش کنید...' );
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_Saderat', 'bps_process_payment');
/////----------------------------------------------------
function bps_verify() //.inja
{
    //var_dump($_POST);
	//error_reporting(0);
	global $edd_options;
     
	$terminalId = $edd_options['Saderat_TermID'];
	$userName = $edd_options['Saderat_UserName'];
	$userPassword = $edd_options['Saderat_PassWord'];

	$bps_ws = 'https://acquirer.samanepay.com/payments/referencepayment.asmx?WSDL';
    
	if (isset($_GET['order']) && $_GET['order'] == 'Saderat' && isset($_POST['TRACENO']) && $_SESSION['Saderat_payment'] == substr($_POST['ResNum'],10) && $_POST['State'] == "OK") 
    {
		  $payment = $_SESSION['Saderat_payment'];
          $State = $_POST['State'];
          $TraceNo = $_POST['TRACENO'];//شماره رهگیری
          $RefNum = $_POST['RefNum'];//رسید دیجیتالی
          $ResNum = $_POST['ResNum']; //orderid
          
		$do_inquiry = false;
		$do_settle = false;
		$do_reversal = false;
		$do_publish = false;
        
        
        
		//Connect to WebService
		$i=0;
		do
        {
			$BpsWs = new nusoap_client($bps_ws,'wsdl');
            $soapProxy = $BpsWs->getProxy();
			$i++;
		}
        while ( $BpsWs->getError() and $i<5 );//Check for connection errors
		if ($BpsWs->getError())
        {
			edd_set_error( 'ver_00', 'V00:تراکنش ناموفق بود.<br>اگر وجهی از حساب شما کسر شده باشد، تا پایان روز جاری به حساب شما باز خواهد گشت.' );
			edd_update_payment_status($_SESSION['Saderat_payment'], 'failed');
			edd_insert_payment_note( $_SESSION['Saderat_payment'], 'V00:'.'<pre>'.$BpsWs->getError().'</pre>' );
			edd_send_back_to_checkout('?payment-mode=Saderat');
		}
//////////////////VERIFY REQUEST///////////////////////
		if (!edd_is_test_mode()) 
        {
			// Call the SOAP method
            $VerResult =  $soapProxy->VerifyTransaction($RefNum, $terminalId);
			if ($VerResult > 0) 
            {
				// Note: Successful Verify means complete successful sale was done.
				$do_reversal = false;
                $do_publish = true;
                //pardakht shod == پرداخت شد
                
                
			
            }
            else
            {
				$do_reversal = true;
                $do_publish = false;
			}
		}
        else 
        {
			//in test mode
			$do_reversal = true;
			$do_publish = false;
		}
///*************************END of VERIFY REQUEST**///

//////////////////REVERSAL REQUEST////////////////////
		if ($do_reversal)
        {
			$i=0;
			do 
            {
                //REVERSAL REQUEST
            $soapclient = new nusoap_client('https://acquirer.samanepay.com/payments/referencepayment.asmx?WSDL','wsdl');
            #$soapclient->debug_flag=true;
            $soapProxy = $soapclient->getProxy();
            if( $err = $soapclient->getError())
            {
    			edd_set_error( 'rev_00', 'R00:تراکنش ناموفق بود.<br>اگر وجهی از حساب شما کسر شده باشد، تا پایان روز جاری به حساب شما باز خواهد گشت.' );
    			edd_update_payment_status($_SESSION['Saderat_payment'], 'failed');
    			edd_insert_payment_note( $_SESSION['Saderat_payment'], 'R00:'.'<pre>'.$err.'</pre>' );
    			edd_send_back_to_checkout('?payment-mode=Saderat');
            }
            #echo $soapclient->debug_str;
            
            $res =  $soapProxy->reverseTransaction($RefNum, $terminalId, $userName, $userPassword);
			$i++;
			}
            while ($res != 1 and $i<5);
			// Note: Successful Reversal means that sale is reversed.
			edd_update_payment_status($payment, 'failed');
			edd_insert_payment_note( $payment, 'REV:'.bps_CheckStatus((int)$res) );
			edd_set_error( 'rev_'.$res, 'R00:تراکنش ناموفق بود.<br>اگر وجهی از حساب شما کسر شده باشد، تا پایان روز جاری به حساب شما باز خواهد گشت.' );
			edd_send_back_to_checkout('?payment-mode=Saderat');
			$do_publish = false;
			$do_reversal = false;
		}
///***************END of REVERSAL REQUEST*******************///
		if ($do_publish == true) 
        {
			// Publish Payment
            $_SESSION['bsi_TraceNo'] = $TraceNo;
            bsi_insert_in_payment();
            
			$do_publish = false;
			edd_update_payment_status($payment, 'publish');
			edd_insert_payment_note( $payment, 'شماره تراکنش:'.$TraceNo );
            
            
            
			echo "<script type='text/javascript'>alert('کد تراکنش خرید بانک : ".$TraceNo."');</script>";
            
            //
		}
	}
    else if (isset($_GET['order']) and $_GET['order'] == 'Saderat' and isset($_POST['TRACENO']) and $_SESSION['Saderat_payment'] == substr($_POST['ResNum'],10) and $_POST['State'] != 'OK')
    {
  		edd_update_payment_status($_SESSION['Saderat_payment'], 'failed');
		edd_insert_payment_note($_SESSION['Saderat_payment'], 'V02:'.bps_CheckStatus((int)$_POST['State']) );
		edd_set_error( $_POST['State'], bps_CheckStatus((int)$_POST['State']) );
		edd_send_back_to_checkout('?payment-mode=Saderat');
	}	
}
add_action('init', 'bps_verify');
/////-----------------------------------------------
function bps_add_settings ($settings) {
	$Saderat_settings = array (
		array (
			'id'		=>	'Saderat_settings',
			'name'		=>	'<strong>پيکربندي درگاه بانک صادرات</strong><br>(در حالت آزمایشی این قسمت را تکمیل نکنید)',
			'desc'		=>	'پيکربندي درگاه بانک صادرات ایران با تنظيمات فروشگاه',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'Saderat_TermID',
			'name'		=>	'کد فروشنده',
			'desc'		=>	'',
			'type'		=>	'text',
			'size'		=>	'medium'
		),
		array (
			'id'		=>	'Saderat_UserName',
			'name'		=>	'نام کاربري',
			'desc'		=>	'',
			'type'		=>	'text',
			'size'		=>	'medium'
		),
		array (
			'id'		=>	'Saderat_PassWord',
			'name'		=>	'رمز',
			'desc'		=>	'',
			'type'		=>	'text',
			'size'		=>	'medium'
		)
	);
	return array_merge( $settings, $Saderat_settings );
}
add_filter('edd_settings_gateways', 'bps_add_settings');
/////-------------------------------------------------
function bps_CheckStatus($ecode) {
	$tmess="شرح خطا: ";
	switch ($ecode) 
	{
	       ////Requset errors
		case -1:
			$tmess.= "خطا در پردازش اطلاعات ارسالی";
			break;
		case -2:
			$tmess.= "خطا در اتصال به سامانه بانکی";
			break;
		case -3:
			$tmess.= "ورودیها حاوی کارکترهای غیرمجاز می‌باشند.";
			break;
        case -4:
            $tmess.= "Merchant Authentication Failed ( کلمه عبور یا کد فروشنده اشتباه است).";
        break;  
        case -6:
            $tmess.= "سند قبلا برگشت کامل یافته است.";
        break;  
        
        case -7:
            $tmess.= "رسید دیجیتالی تهی است.";
        break;  
        
        case -8:
            $tmess.= "طول ورودی ها بیشتر از حد مجاز است.";
        break;  
        
        case -9:
            $tmess.= "وجود کارکترهای غیرمجاز در مبلغ برگشتی.";
        break;  
        
        case -10:
            $tmess.= "رسید دیجیتالی به صورت Base64 نیست(حاوی کاراکترهای غیرمجاز است).";
        break;  
        
        case -11:
            $tmess.= "طول ورودی‌ها بیشتر از حد مجاز است.";
        break;  
        
        case -12:
            $tmess.= "مبلغ برگشتی منفی است.";
        break; 
         
        case -13:
            $tmess.= "مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت نخورده ی رسید دیجیتالی است.";
        break;   
        
        case -14:
            $tmess.= "چنین تراکنشی تعریف نشده است.";
        break;  
        
        case -15:
            $tmess.= "مبلغ برگشتی به صورت اعشاری داده شده است.";
        break;  
        
        case -16:
            $tmess.= "خطای داخلی سیستم";
        break;  
        
        case -17:
            $tmess.= "برگشت زدن جزیی تراکنش مجاز نمی باشد.";
        break;  
        
        case -18:
            $tmess.= "IP Address فروشنده نا معتبر است.";
        break;
            //verify errors
        case "Canceled By User":
        $tmess.= "تراکنش توسط خریدار کنسل شده است.";
        break;
        
        case "Invalid Amount":
        $tmess.= "مبلغ سند برگشتی، از مبلغ تراکنش اصلی بیشتر است.";
        break;  
            
        case "Invalid Transaction":
        $tmess.= " برگشت یک تراکنش رسیده است، در حالی که تراکنش اصلی پیدا نمی شود.";
        break;  
        
        case "Invalid Card Number":
        $tmess.= "شماره کارت اشتباه است.";
        break;  
        
        case "No Such Issuer":
        $tmess.= "چنین صادر کننده کارتی وجود ندارد.";
        break;  
        
        case "Expired Card Pick Up":
        $tmess.= "از تاریخ انقضای کارت گذشته است و کارت دیگر معتبر نیست.";
        break;  
        
        case "Allowable PIN Tries Exceeded Pick Up":
        $tmess.= "رمز کارت( PIN ) بیش از 3 مرتبه اشتباه وارد شده است در نتیجه کارت غیرفعال خواهد شد.";
        break;  
        
        case "Incorrect PIN":
        $tmess.= "خریدار رمز کارت ( PIN ) را اشتباه وارد کرده است.";
        break;  
        
        case "Exceeds Withdrawal Amount Limit":
        $tmess.= "مبلغ بیش از سقف برداشت می باشد.";
        break;  
        
        case "Transaction Cannot Be Completed":
        $tmess.= "تراکنش Authorize شده است (شماره PIN و PAN درست هستند) ولی امکان سند خوردن وجود ندارد.";
        break; 
         
        case "Response Received Too Late":
        $tmess.= "تراکنش در شبکه بانکی Timeout خورده است.";
        break;   
        
        case "Suspected Fraud Pick Up":
        $tmess.= "خریدار یا فیلد CVV2 و یا فیلد ExpDate را اشتباه وارد کرده است (یا اصلا وارد نکرده است).";
        break;  
        
        case "No Sufficient Funds":
        $tmess.= "موجودی حساب خریدار، کافی نیست.";
        break;  
        
        case "Issuer Down Slm":
        $tmess.= "سیستم بانک صادر کننده کارت خریدار، در وضعیت عتلیاتی نیست.";
        break;  
        
        case "TME Error":
        $tmess.= "کلیه خطاهای دیگر بانکی باعث ایجاد چنین خطایی می گردد.";
        break;  
            
		default:
			$tmess.= "خطای تعریف نشده";
	}	
	return $ecode.': '.$tmess;
}
?>