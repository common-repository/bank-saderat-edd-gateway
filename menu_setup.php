<?php 

add_action('admin_menu', 'bps_setMenu');


		
function bps_setMenu(  )
{

	add_menu_page('<span>بانک صادرات</span>', '<span>بانک صادرات</span>', 'activate_plugins', "saderat_bank_gate", 'bps_load_inteface', plugin_dir_url( __FILE__ ).'/images/icon.png'); 
	add_submenu_page("saderat_bank_gate", '<span>درباره ما</span>', '<span>درباره ما</span>', 'activate_plugins', "saderat_bank_gate_about", "bps_load_about");
	add_submenu_page("saderat_bank_gate", '<span>خبرنامه</span>', '<span>خبرنامه</span>', 'activate_plugins', "saderat_bank_gate_news", "bps_load_news");

}


function bps_load_inteface(  )
{
	include dirname(__file__)."/saderat.php";
}
function bps_load_about(  )
{
	include dirname(__file__)."/about.php";
}
function bps_load_news(  )
{
	include dirname(__file__)."/news.php";
}