<?php
require APP_ROOT_PATH.'wap/app/page.php';
require APP_ROOT_PATH.'app/Lib/shop_lip.php';
class accountModule{
	 function __construct() {
        if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		$GLOBALS['tmpl']->assign('now',NOW_TIME);
     }
	
	public function index()
	{	
		 
		$GLOBALS['tmpl']->assign("page_title","支持的项目");
		$page_size = intval($GLOBALS['m_config']['page_size']);;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$order_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."deal_order where user_id = ".intval($GLOBALS['user_info']['id'])." and (type=0 or type=2) order by create_time desc limit ".$limit);
		foreach($order_list as $k=>$v){
			if($v['repay_make_time']==0&&$v['repay_time']>0){
				$left_date=intval(app_conf("REPAY_MAKE"))?7:intval(app_conf("REPAY_MAKE"));
				$repay_make_date=$v['repay_time']+$left_date*24*3600;
				if($repay_make_date<=get_gmtime()){
 					$GLOBALS['db']->query("update ".DB_PREFIX."deal_order set repay_make_time =  ".get_gmtime()." where id = ".$v['id'] );
					$order_list[$k]['repay_make_time']=get_gmtime();
				}
			}
		}
		$order_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal_order where user_id = ".intval($GLOBALS['user_info']['id'])." and (type=0 or type=2)");
		
		$page = new Page($order_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$deal_ids=array();
		foreach($order_list as $k=>$v){
			$deal_ids[] =  $v['deal_id'];
		}
		if($deal_ids!=null){
			$deal_list_array=$GLOBALS['db']->getAll("select * from ".DB_PREFIX."deal where  is_effect = 1 and is_delete = 0 and id in (".implode(',',$deal_ids).")  and (type=0 or type=2) ");
			$deal_list=array();
			foreach($deal_list_array as $k=>$v){
				if($v['id']){
					$deal_list[$v['id']]=$v;
				}
			}
			foreach($order_list as $k=>$v)
			{
	 			$order_list[$k]['deal_info'] =$deal_list[$v['deal_id']];
			}
			 
			$GLOBALS['tmpl']->assign('order_list',$order_list);
		}
 		
		$GLOBALS['tmpl']->display("account_index.html");
	}
	public function view_order()
	{
		$id = intval($_REQUEST['id']);
		$order_info = $GLOBALS['db']->getRow("select deo.*,d.transfer_share as transfer_share,d.limit_price as limit_price from ".DB_PREFIX."deal_order as deo LEFT JOIN ".DB_PREFIX."deal as d on deo.deal_id = d.id where deo.id = ".$id." and deo.user_id = ".intval($GLOBALS['user_info']['id']));
		if(!$order_info)
		{
			showErr("无效的项目支持",0,get_gopreview_wap());
		}
		
		//========如果超过系统设置的时间，则自动设置收到回报 start
		if($order_info['repay_make_time']==0){
			$item=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal_item where id=".$order_info['deal_item_id']);
			$item_day=intval($item['repaid_day']);
			if($item_day>0){
				$left_date=$item_day;
			}else{
				$left_date=intval(app_conf("REPAY_MAKE"));
			}
				
			$repay_make_date=$order_info['repay_time']+$left_date*24*3600;
			if($repay_make_date>get_gmtime()&&$order_info['repay_time']>0){
				$order_info['repay_make_date']=date('Y-m-d H:i:s',$repay_make_date);
			}else{
 				$GLOBALS['db']->query("update ".DB_PREFIX."deal_order set repay_make_time =  ".get_gmtime()." where id = ".$id);
				$order_info['repay_make_time']=get_gmtime();
			}
		}
		if($order_info['type']==1){
			//用户所占股份
			$order_info['user_stock']= number_format(($order_info['total_price']/$order_info['limit_price'])*$order_info['transfer_share'],2);			
			//项目金额
			$order_info['stock_value'] =number_format($order_info['limit_price'],2);
			//应付金额
			//$order_info['total_price'] =number_format($order_info['total_price'],2);
		}
		//=============如果超过系统设置的时间，则自动设置收到回报 end
		$GLOBALS['tmpl']->assign("order_info",$order_info);
		$deal_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id = ".$order_info['deal_id']." and is_delete = 0 and is_effect = 1");
		$GLOBALS['tmpl']->assign("deal_info",$deal_info);
		
		if($order_info['order_status'] == 0)
		{
			$payment_list = get_payment_list("wap");
			
			$GLOBALS['tmpl']->assign("payment_list",$payment_list);
			
			$max_pay = $order_info['total_price'];
			$GLOBALS['tmpl']->assign("max_pay",$max_pay);
			
			$order_sm=array('credit_pay'=>0,'score'=>0,'score_money'=>0);
			if($order_info['credit_pay']>0)
			{
				$order_sm['credit_pay']=$order_info['credit_pay'] <= $GLOBALS['user_info']['money']?$order_info['credit_pay']:$GLOBALS['user_info']['money'];
			}
			if($order_info['score'] >0)
			{
				
				if($order_info['score'] <= $GLOBALS['user_info']['score'])
				{
					$order_sm['score']=$order_info['score'];
					$order_sm['score_money']=$order_info['score_money'];
				}else
				{
					$order_sm['score']=$GLOBALS['user_info']['score'];
					$score_array=score_to_money($GLOBALS['user_info']['score']);
					$order_sm['score_money']=$score_array['score_money'];
					$order_sm['score']=$score_array['score'];
				}
			}
			$GLOBALS['tmpl']->assign("order_sm",json_encode($order_sm));
			$GLOBALS['tmpl']->assign("page_title","订单支付");
		}else{
			$GLOBALS['tmpl']->assign("page_title","订单详情");
		}
		$GLOBALS['tmpl']->assign("coll",is_tg(true));
		
		$GLOBALS['tmpl']->display("account_view_order.html");
	}
	
	public function del_order()
	{
		$ajax = intval($_REQUEST['ajax']);
		if(!$GLOBALS['user_info'])
		{
			showErr("",$ajax,url_wap("user#login"));
		}
		$order_id = intval($_REQUEST['id']);
		$order_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal_order where order_status = 0 and user_id = ".intval($GLOBALS['user_info']['id'])." and id = ".$order_id);
		if(!$order_info)
		{
			showErr("无效的订单",$ajax,"");
		}
		else
		{
			$money = $order_info['credit_pay'];
			$GLOBALS['db']->query("delete from ".DB_PREFIX."deal_order where id = ".$order_id." and user_id = ".intval($GLOBALS['user_info']['id'])." and order_status = 0");
			if($GLOBALS['db']->affected_rows()>0)
			{
				if($money>0)
				{
					require_once APP_ROOT_PATH."system/libs/user.php";
					modify_account(array("money"=>$money),intval($GLOBALS['user_info']['id']),"删除".$order_info['deal_name']."项目支付，退回支付款。");						
				}
			}
			showSuccess("",$ajax,get_gopreview());
		}
	}
	public function go_order_pay(){
		$id = intval($_REQUEST['order_id']);
		$order_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal_order where id = ".$id." and user_id = ".intval($GLOBALS['user_info']['id'])." and order_status = 0");
		$paypassword=strim($_REQUEST['paypassword']);
		if($paypassword==''){
			showErr("请输入支付密码",0);	
		}
		if(md5($paypassword)!=$GLOBALS['user_info']['paypassword']){
			showErr("支付密码错误",0);	
		}
		$is_tg=intval($_REQUEST['is_tg']);
		if($is_tg){
			if(!$GLOBALS['is_user_tg']){
				$jump_url=get_domain().APP_ROOT."/wap/index.php?ctl=collocation&act=CreateNewAcct&user_type=0&user_id=".$GLOBALS['user_info']['id'];
				showErr("您未绑定第三方接口无法支付,点击确定后跳转到绑定页面",0,$jump_url);
			}
		}
		if(!$order_info)
		{
			showErr("项目支持已支付",0,get_gopreview_wap());
		}
		else
		{
			$credit = floatval($_REQUEST['credit']);
			$payment_id = intval($_REQUEST['payment']);
			$pay_score =intval($_REQUEST['pay_score']);
			$score_trade_number=intval(app_conf("SCORE_TRADE_NUMBER"))>0?intval(app_conf("SCORE_TRADE_NUMBER")):0;
			$pay_score_money=intval($pay_score/$score_trade_number*100)/100;
			if(!$is_tg)
			{
				if($credit> $GLOBALS['user_info']['money'])
					showErr("余额最多只能用".format_price($GLOBALS['user_info']['money']),0);
				if($pay_score > $GLOBALS['user_info']['score'])
					showErr("积分最多只能用".$GLOBALS['user_info']['score']);
				if($pay_score_money+ $credit > $order_info['total_price'])
					showErr("支付超出");
			}
				
//			if($credit>0)
//			{				
//				$max_pay = $order_info['total_price'] - $order_info['credit_pay'];
//				$max_credit= $max_pay<$GLOBALS['user_info']['money']?$max_pay:$GLOBALS['user_info']['money'];
//				$credit = $credit>$max_credit?$max_credit:$credit;		
//				if($credit>0)
//				{	
//				$GLOBALS['db']->query("update ".DB_PREFIX."deal_order set credit_pay = credit_pay + ".$credit." where id = ".$order_info['id']);//追加使用余额支付
//				
//				require_once APP_ROOT_PATH."system/libs/user.php";
//				modify_account(array("money"=>"-".$credit),intval($GLOBALS['user_info']['id']),"支持".$order_info['deal_name']."项目支付");		
//				}		
//			}
			if($credit>0){
				$order_data['credit_pay'] = $credit;
			}else
			{
				$order_data['credit_pay'] = 0;
			}
			if($pay_score>0)
			{
				$order_data['score'] = $pay_score;
				$order_data['score_money'] = $pay_score_money;
			}
			else
			{
				$order_data['score'] = 0;
				$order_data['score_money'] = 0;
			}
			$GLOBALS['db']->query("update ".DB_PREFIX."deal_order set credit_pay = ".$order_data['credit_pay'].",score=".$order_data['score'].",score_money=".$order_data['score_money']." where id = ".intval($order_info['id'])." ");
			
			$result = pay_order($order_info['id']);

			if($result['status']==0)
			{
				if($is_tg){
					$sign=md5(md5($paypassword).$order_info['id']);
 					$url=get_domain().APP_ROOT."/wap/index.php?ctl=collocation&act=RegisterCreditor&order_id=".$order_info['id']."&sign=".$sign;
 					//showSuccess("",0,$url);
 					app_redirect($url);
					
				}else{
					$money = $result['money'];
					$payment_notice['create_time'] = NOW_TIME;
					$payment_notice['user_id'] = intval($GLOBALS['user_info']['id']);
					$payment_notice['payment_id'] = $payment_id;
					$payment_notice['money'] = $money;
					$payment_notice['bank_id'] = strim($_REQUEST['bank_id']);
					$payment_notice['order_id'] = $order_info['id'];
					$payment_notice['memo'] = $order_info['support_memo'];
					$payment_notice['deal_id'] = $order_info['deal_id'];
					$payment_notice['deal_item_id'] = $order_info['deal_item_id'];
					$payment_notice['deal_name'] = $order_info['deal_name'];
					
					do{
						$payment_notice['notice_sn'] = to_date(NOW_TIME,"Ymd").rand(100,999);
						$GLOBALS['db']->autoExecute(DB_PREFIX."payment_notice",$payment_notice,"INSERT","","SILENT");
						$notice_id = $GLOBALS['db']->insert_id();
					}while($notice_id==0);
					
			
					app_redirect(url_wap("cart#jump",array("id"=>$notice_id,"from"=>strim($_REQUEST['from']))));
				}
			}
			else
			{
				app_redirect(url_wap("account#view_order",array("id"=>$order_info['id'])));  
			}
		}
	}
	
	public function project()
	{
 		$GLOBALS['tmpl']->assign("page_title","我的项目列表");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));	

		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
 		if (app_conf("INVEST_STATUS")==1||app_conf("INVEST_STATUS")==0)
		{	
			$condition = " type=0 ";	
		}
		elseif (app_conf("INVEST_STATUS")==2)
		{
			showErr("产品众筹已经关闭");
		}

		$deal_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."deal where $condition and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0 order by id desc,create_time desc limit ".$limit);
		$deal_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where $condition and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
		
		$deal_gq_sum = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where type=1 and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
		$GLOBALS['tmpl']->assign('deal_gq_sum',$deal_gq_sum);
		$deal_cp_sum = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where type=0 and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
		$GLOBALS['tmpl']->assign('deal_cp_sum',$deal_cp_sum);
		foreach($deal_list as $k=>$v)
		{
			$deal_list[$k]['remain_days'] = ceil(($v['end_time'] - NOW_TIME)/(24*3600));
			$deal_list[$k]['percent'] = round($v['support_amount']/$v['limit_price']*100,2);
			if($v['type']== 0){
				$deal_list[$k]['support_count']= $deal_list[$k]['support_count']+ $deal_list[$k]['virtual_num'];
			}
		}
		
		$page = new Page($deal_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('deal_list',$deal_list);
		
		$GLOBALS['tmpl']->display("account_project.html");
	}
	public function project_invest(){
		
		 
		$GLOBALS['tmpl']->assign("page_title","我的项目列表");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));	

		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
 		if (app_conf("INVEST_STATUS")==2||app_conf("INVEST_STATUS")==0)
		{	
			$condition = " type=1 ";	
		}
		elseif (app_conf("INVEST_STATUS")==1)
		{
			showErr("股权众筹已经关闭");
		} 	
		
		$deal_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."deal where $condition and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0 order by id desc,create_time desc limit ".$limit);
		$deal_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where $condition and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
			
		$deal_gq_sum = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where type=1 and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
		$GLOBALS['tmpl']->assign('deal_gq_sum',$deal_gq_sum);
		$deal_cp_sum = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal where type=0 and user_id = ".intval($GLOBALS['user_info']['id'])." and is_delete = 0");
		$GLOBALS['tmpl']->assign('deal_cp_sum',$deal_cp_sum);

		foreach($deal_list as $k=>$v)
		{
			$deal_list[$k]['remain_days'] = ceil(($v['end_time'] - NOW_TIME)/(24*3600));
			$deal_list[$k]['percent'] = round($v['invote_money']/$v['limit_price']*100,2);
			if($v['type']== 0){
				$deal_list[$k]['support_count']= $deal_list[$k]['support_count']+ $deal_list[$k]['virtual_num'];
			}
		}		
		$page = new Page($deal_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('deal_list',$deal_list);
		
		$GLOBALS['tmpl']->display("account_project_invest.html");
	}
	
	public function focus()
	{
 		$GLOBALS['tmpl']->assign("page_title","关注的项目");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));		
		
		$page_size = intval($GLOBALS['m_config']['page_size']);;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$s = intval($_REQUEST['s']);
		if($s==3)
		$sort_field = " d.support_amount desc ";
		if($s==1)
		$sort_field = " d.support_count desc ";
		if($s==2)
		$sort_field = " d.support_amount - d.limit_price desc ";
		if($s==0)
		$sort_field = " d.end_time asc ";
		
		$GLOBALS['tmpl']->assign("s",$s);
		
		$f = intval($_REQUEST['f']);
		if($f==0)
		$cond = " 1=1 ";
		if($f==1)
		$cond = " d.begin_time < ".NOW_TIME." and (d.end_time = 0 or d.end_time > ".NOW_TIME.") ";
		if($f==2)
		$cond = " d.end_time <> 0 and d.end_time < ".NOW_TIME." and d.is_success = 1 "; //过期成功
		if($f==3)
		$cond = " d.end_time <> 0 and d.end_time < ".NOW_TIME." and d.is_success = 0 "; //过期失败
		$GLOBALS['tmpl']->assign("f",$f);
		
		
		
		$app_sql = " ".DB_PREFIX."deal_focus_log as dfl left join ".DB_PREFIX."deal as d on d.id = dfl.deal_id where dfl.user_id = ".intval($GLOBALS['user_info']['id']).
				   " and d.is_effect = 1 and d.is_delete = 0 and ".$cond." ";
		if(app_conf('INVEST_STATUS') == 0)
		{
			$deal_list = $GLOBALS['db']->getAll("select d.*,dfl.id as fid from ".$app_sql." order by ".$sort_field." limit ".$limit);
			$deal_count = $GLOBALS['db']->getOne("select count(*) from ".$app_sql);
		}
		elseif(app_conf('INVEST_STATUS') == 1)
		{
			$deal_list = $GLOBALS['db']->getAll("select d.*,dfl.id as fid from ".$app_sql." and d.type =0 order by ".$sort_field." limit ".$limit);
			$deal_count = $GLOBALS['db']->getOne("select count(*) from ".$app_sql." and d.type =0");
		}
		else
		{
			$deal_list = $GLOBALS['db']->getAll("select d.*,dfl.id as fid from ".$app_sql." and d.type =1 order by ".$sort_field." limit ".$limit);
			$deal_count = $GLOBALS['db']->getOne("select count(*) from ".$app_sql." and d.type =1");
		}
		
		foreach($deal_list as $k=>$v)
		{
			$deal_list[$k]['remain_days'] = ceil(($v['end_time'] - NOW_TIME)/(24*3600));
			$deal_list[$k]['percent'] = round($v['support_amount']/$v['limit_price']*100);
			if($v['type']== 0){
				
				$deal_list[$k]['support_amount']= $deal_list[$k]['support_amount']+ $deal_list[$k]['virtual_price'];
				$deal_list[$k]['percent'] = round($deal_list[$k]['support_amount']/$v['limit_price']*100,2);
				$deal_list[$k]['support_count']= $deal_list[$k]['support_count']+ $deal_list[$k]['virtual_num'];
			}
			if($v['type']== 1){
				$deal_list[$k]['percent']= round($v['invote_money']/$v['limit_price']*100,2);
			}
		}
		
		$page = new Page($deal_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);

		$GLOBALS['tmpl']->assign('deal_list',$deal_list);
		
		$GLOBALS['tmpl']->display("account_focus.html");
	}
	public function del_focus()
	{
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$id = intval($_REQUEST['id']);
		$deal_id = $GLOBALS['db']->getOne("select deal_id from ".DB_PREFIX."deal_focus_log where id = ".$id." and user_id = ".intval($GLOBALS['user_info']['id']));
		$GLOBALS['db']->query("delete from ".DB_PREFIX."deal_focus_log where id = ".$id." and user_id = ".intval($GLOBALS['user_info']['id']));
		$GLOBALS['db']->query("update ".DB_PREFIX."deal set focus_count = focus_count - 1 where id = ".intval($deal_id));
		$GLOBALS['db']->query("delete from ".DB_PREFIX."user_deal_notify where user_id = ".intval($GLOBALS['user_info']['id'])." and deal_id = ".$deal_id);
							
		app_redirect(get_gopreview_wap());
	}
	public function credit(){
		$GLOBALS['tmpl']->assign("page_title","收支明细");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = intval($GLOBALS['m_config']['page_size']);
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$log_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_log where money != 0 and user_id = ".intval($GLOBALS['user_info']['id'])." order by log_time desc limit ".$limit);
 		$log_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."user_log where money != 0 and user_id = ".intval($GLOBALS['user_info']['id']));
  		$page = new Page($log_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		foreach($log_list as $k=>$v){
			$log_list[$k]['money']=floatval($log_list[$k]['money']);
			if($log_list[$k]['money']>0){
				$log_list[$k]['money_type']="增加";
			}else{
				$log_list[$k]['money_type']="减少";
			}
		}
		$GLOBALS['tmpl']->assign('log_list',$log_list);
		 
 		$GLOBALS['tmpl']->display("account_credit.html");
	}
	//积分明细
	public function score()
	{
		$GLOBALS['tmpl']->assign("page_title","积分明细");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = intval($GLOBALS['m_config']['page_size']);
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$log_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_log where user_id = ".intval($GLOBALS['user_info']['id'])." and score <>0  order by log_time desc,id desc limit ".$limit);
		$log_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."user_log where user_id = ".intval($GLOBALS['user_info']['id'])." and score <>0");
	
		$page = new Page($log_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);	
		$GLOBALS['tmpl']->assign('log_list',$log_list);
		$GLOBALS['tmpl']->assign('total_score',$GLOBALS['user_info']['score']);
		$GLOBALS['tmpl']->display("account_score.html");
	}
	//信用明细
	public function point()
	{
        //links
		$GLOBALS['tmpl']->assign("page_title","信用明细");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = intval($GLOBALS['m_config']['page_size']);
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$log_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_log where user_id = ".intval($GLOBALS['user_info']['id'])." and point <>0  order by log_time desc,id desc limit ".$limit);
		$log_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."user_log where user_id = ".intval($GLOBALS['user_info']['id'])." and point <>0");
	
		$page = new Page($log_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('log_list',$log_list);
		$GLOBALS['tmpl']->assign('total_point',$GLOBALS['user_info']['point']);
		$GLOBALS['tmpl']->display("account_point.html");
	}
	public function record(){
 		
		if(!$GLOBALS['user_info'])
			app_redirect(url_wap("user#login"));
		$GLOBALS['tmpl']->assign("page_title","充值记录");

		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
			$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		//SELECT * FROM fanwe_payment_notice n WHERE n.order_id='0' AND n.deal_id='0' AND n.deal_item_id='0' AND deal_name='';
		
		$condition=" and order_id=0 AND deal_id=0 AND deal_item_id=0 AND deal_name='' AND is_paid=1 ";
		$record_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."payment_notice where user_id = ".intval($GLOBALS['user_info']['id'])." $condition  order by create_time desc limit ".$limit);
		$record_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."payment_notice where user_id = ".intval($GLOBALS['user_info']['id'])." $condition");
		$total_money = $GLOBALS['db']->getOne("select sum(money) from ".DB_PREFIX."payment_notice where user_id = ".intval($GLOBALS['user_info']['id'])." $condition");
		foreach($record_list as $k=>$v){
			if(!$v['is_paid']){
				$record_list[$k]['url']=url_wap("account#record_pay",array("notice_id"=>$v['id']));
			}
		}
		$page = new Page($record_count,$page_size);   //初始化分页对象
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		
		$GLOBALS['tmpl']->assign('total_money',floatval($total_money));
		/*
		foreach($record_list as $k=>$v)
		{
			$record_list[$k]['deal_info'] = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id = ".$v['deal_id']." and is_effect = 1 and is_delete = 0");
		}
		*/
		$GLOBALS['tmpl']->assign('record_list',$record_list);
		$GLOBALS['tmpl']->display("account_record.html");
	}
 	public function record_pay(){
 		$id=intval($_REQUEST['notice_id']);
 		$payment_info=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where id=".$id);
 		$payment_list=get_payment_list("wap");
  		$GLOBALS['tmpl']->assign("page_title","充值");
  		$GLOBALS['tmpl']->assign('payment_info',$payment_info);
  		$GLOBALS['tmpl']->assign('payment_list',$payment_list);
  		$GLOBALS['tmpl']->display("account_record_pay.html");
  	}
  	public function record_go_pay(){
  		$return=array('status'=>1,'info'=>'','jump'=>'');
 		$id=intval($_REQUEST['notice_id']);
 		$payment_id=intval($_REQUEST['payment_id']);
 		
 		$GLOBALS['db']->query("update ".DB_PREFIX."payment_notice set payment_id=$payment_id where id=$id ");
 		$return['jump']=url_wap("account#jump",array('id'=>$id));
 		
 		ajax_return($return);
  	}
 	public function incharge(){
 		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		$GLOBALS['tmpl']->assign("money",floatval($_REQUEST['money']));
		$payment_list = get_payment_list("wap");
		$GLOBALS['tmpl']->assign("payment_list",$payment_list);
		$GLOBALS['tmpl']->assign("page_title","充值");
 		$GLOBALS['tmpl']->display("account_incharge.html");
 	}
 	public function do_incharge()
	{	
 		$ajax = intval($_REQUEST['ajax']);
		if(!$GLOBALS['user_info'])
		{
			showErr("",$ajax,url_wap("user#login"));
		}
		$money = floatval($_REQUEST['money']);
		if($money<=0)
		{
			showErr("充值的金额不正确",$ajax,"");
		}
 		showSuccess("",$ajax,url_wap("account#pay",array("money"=>round($money*100))));
	}
 	public function pay(){
		$money = floatval(intval($_REQUEST['money'])/100);
		if($money<=0)
		{
			app_redirect(url_wap("account#incharge"));
		}
		
		$GLOBALS['tmpl']->assign("money",$money);
		$payment_list = get_payment_list("wap");
		$GLOBALS['tmpl']->assign("payment_list",$payment_list);
		$GLOBALS['tmpl']->display("account_pay.html");
	}
	//冻结余额
	public function ye_mortgage_pay(){
		$ajax = intval($_REQUEST['ajax']);
		//print_r($_REQUEST);
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		$paypassword=strim($_REQUEST['paypassword']);
		if($paypassword==''){
			showErr("请输入支付密码",0);	
		}
		if(md5($paypassword)!=$GLOBALS['user_info']['paypassword']){
			showErr("支付密码错误",0);	
		}
		$deal_id=intval($_REQUEST['deal_id']);
		$money = floatval($_REQUEST['money']);
		if($GLOBALS['user_info']['money']>=$money){
			$re = set_mortgate($GLOBALS['user_info']['id'],$deal_id,$money);
			if($re){
				syn_mortgate($GLOBALS['user_info']['id']);
				showSuccess("冻结成功",0,url_wap("deal#index",array("id"=>$deal_id)));	
			}else{
				showErr("冻结失败",0,url_wap("deal#index",array("id"=>$deal_id)));	
			}
		}else{
			showErr("您的余额不够",0);	
		}
		
	}
	public function go_pay()
	{
		$ajax = intval($_REQUEST['ajax']);
		$is_mortgate=intval($_REQUEST['is_mortgate']);
		if($is_mortgate==1){
			$paypassword=strim($_REQUEST['paypassword']);
			if($paypassword==''){
				showErr("请输入支付密码",0);	
			}
			if(md5($paypassword)!=$GLOBALS['user_info']['paypassword']){
				showErr("支付密码错误",0);	
			}
		}
		
		$is_tg=intval($_REQUEST['is_tg']);
		$deal_id=intval($_REQUEST['deal_id']);
		
		$pTrdAmt=doubleval($_REQUEST['money']);
		if($is_tg){
			if($GLOBALS['is_user_tg']){
				$jump_url=get_domain().APP_ROOT."/wap/index.php?ctl=collocation&act=SincerityGoldFreeze&user_type=0&user_id=".$GLOBALS['user_info']['id']."&pTrdAmt=".$pTrdAmt."&deal_id=".$deal_id."&from=".'wap';
				//showErr("您已经绑定第三方接口，正在支付诚意金,点击确定后跳转",0,$jump_url);
				//app_redirect(url("collocation#SincerityGoldFreeze",array("user_type"=>0,"user_id"=>$GLOBALS['user_info']['id'],"pTrdAmt"=>$pTrdAmt,"&deal_id"=>$deal_id)));
				app_redirect($jump_url);
			}else{
				$jump_url=get_domain().APP_ROOT."/wap/index.php?ctl=collocation&act=CreateNewAcct&user_type=0&user_id=".$GLOBALS['user_info']['id'];
				showErr("您未绑定第三方接口无法支付,点击确定后跳转到绑定页面",0,$jump_url);
			}
		}
 		$payment_id = intval($_REQUEST['payment']);
		if($payment_id==0)
		{
			app_redirect(url_wap("account#pay"));
		}
		
		$money = floatval($_REQUEST['money']);
		if($money<=0)
		{
			app_redirect(url_wap("account#pay"));
		}
		
		$payment_notice['create_time'] = NOW_TIME;
		$payment_notice['user_id'] = intval($GLOBALS['user_info']['id']);
		$payment_notice['payment_id'] = $payment_id;
		$payment_notice['money'] = $money;
		$payment_notice['bank_id'] = strim($_REQUEST['bank_id']);
		if(!empty($_REQUEST['is_mortgate'])){
			$payment_notice['is_mortgate']=intval($_REQUEST['is_mortgate']);
		}
		do{
			$payment_notice['notice_sn'] = to_date(NOW_TIME,"Ymd").rand(100,999);
			$GLOBALS['db']->autoExecute(DB_PREFIX."payment_notice",$payment_notice,"INSERT","","SILENT");
			$notice_id = $GLOBALS['db']->insert_id();
		}while($notice_id==0);
 		app_redirect(url_wap("account#jump",array("id"=>$notice_id,"from"=>strim($_REQUEST['from']))));
		
	}
	public function jump()
	{
 		$notice_id = intval($_REQUEST['id']);
		$notice_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where id = ".$notice_id." and is_paid = 0 and user_id = ".intval($GLOBALS['user_info']['id']));
		if(!$notice_info)
		{
				$data['pay_status'] = 1;
				$data['pay_info'] = '订单生成失败.';
				$data['show_pay_btn'] = 0;
 				$GLOBALS['tmpl']->assign('data',$data);
 				$GLOBALS['tmpl']->display('pay_order_index.html');
		}else{
			$payment_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment where id = ".$notice_info['payment_id']);
			if(!$payment_info){
				$data['pay_status'] = 1;
				$data['pay_info'] = '支付方式不存在.';
				$data['show_pay_btn'] = 0;
 				$GLOBALS['tmpl']->assign('data',$data);
 				$GLOBALS['tmpl']->display('pay_order_index.html');
			}
			$class_name = $payment_info['class_name']."_payment";
			require_once APP_ROOT_PATH."system/payment/".$class_name.".php";
			$o = new $class_name;
			
			//header("Content-Type:text/html; charset=utf-8");
			$pay= $o->get_payment_code($notice_id);
			
			app_redirect($pay['notify_url']);
		}
		
	}
	//提现列表
	public function refund_list(){
		$page_size =intval($GLOBALS['m_config']['page_size']);
		$page = intval($_REQUEST['p']);
		if($page==0)$page = 1;		
		$limit = (($page-1)*$page_size).",".$page_size	;
		$refund_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id'])." order by create_time desc limit ".$limit);
		$refund_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id']));
	
 		$GLOBALS['tmpl']->assign("refund_list",$refund_list);
 		$page = new Page($refund_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->display("account_refund_list.html");
	}
	//提现记录列表
	public function refund()
	{
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}

		$GLOBALS['tmpl']->assign("page_title","提现记录");
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)$page = 1;		
		$limit = (($page-1)*$page_size).",".$page_size	;

		$refund_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id'])." order by create_time desc limit ".$limit);
		$refund_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id']));
	
		
		$GLOBALS['tmpl']->assign("refund_list",$refund_list);

		$page = new Page($refund_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		
		$GLOBALS['tmpl']->display("account_money_carry_log.html");
	}
 	
 	public function submitrefund()
	{
		$ajax = intval($_REQUEST['ajax']);
		if(!$GLOBALS['user_info'])
		{
			showErr("",$ajax,url_wap("user#login"));
		}
		$user_bank_id=intval($_REQUEST['user_bank_id']);
		$money = doubleval($_REQUEST['money']);
		$memo = strim($_REQUEST['memo']);
		
		if($money<=0)
		{
			showErr("提现金额出错",$ajax);
		}
		$paypassword=strim($_REQUEST['paypassword']);
		if($GLOBALS['user_info']['paypassword']!=md5($paypassword)){
			showErr("支付密码错误",$ajax);
		}
		
		$ready_refund_money =doubleval($GLOBALS['db']->getOne("select sum(money) from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id'])." and is_pay = 0"));
		if($ready_refund_money + $money > $GLOBALS['user_info']['money'])
		{
			showErr("提现超出限制",$ajax);
		}
		$refund_data['user_bank_id'] = $user_bank_id;
		$refund_data['money'] = $money;
		$refund_data['user_id'] = $GLOBALS['user_info']['id'];
		$refund_data['create_time'] = NOW_TIME;
		$refund_data['memo'] = $memo;
		$GLOBALS['db']->autoExecute(DB_PREFIX."user_refund",$refund_data);
		
		showSuccess("提交成功",$ajax,url_wap("account#money_carry_bank"));
	}
 	public function delrefund()
	{
		$ajax = intval($_REQUEST['ajax']);
		if(!$GLOBALS['user_info'])
		{
			showErr("",$ajax,url_wap("user#login"));
		}
		
		$id = intval($_REQUEST['id']);
		$GLOBALS['db']->query("delete from ".DB_PREFIX."user_refund where id = ".$id." and user_id = ".intval($GLOBALS['user_info']['id']));
		if($GLOBALS['db']->affected_rows()>0)
		{
			showSuccess("删除成功",$ajax);
		}
		else
		{
			showErr("删除失败",$ajax);
		}
		
	}
	
	public function mortgate_pay()
  	{
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
  		$GLOBALS['tmpl']->assign("page_title","缴纳诚意金");
  	 
  		$deal_id=$_REQUEST['deal_id'];
  	 	$GLOBALS['tmpl']->assign("deal_id",$deal_id);
  	 	$deal_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where is_delete = 0 and is_effect = 1 and id = ".$deal_id);
  	 	$GLOBALS['tmpl']->assign("deal_info",$deal_info);
  		$new_money=user_need_mortgate();
  		$has_money=$GLOBALS['db']->getOne("select sum(amount) from ".DB_PREFIX."money_freeze where platformUserNo=".$GLOBALS['user_info']['id']." and deal_id=".$deal_id." and status=1 ");
   		$money = $new_money-$has_money;
   		if($money<=0)
  		{
  			//app_redirect(url_wap("account#mortgate_incharge"));
  			showSuccess("您的诚意金已支付，无需再支付！");
  		}
  		 
  		$GLOBALS['tmpl']->assign("money",$money);
  		if($money>$GLOBALS['user_info']['money']){
   			$left_money = $money - floatval($GLOBALS['user_info']['money']);
   		}else{
   			$left_money = 0;
   		}
   		$GLOBALS['tmpl']->assign("left_money",$left_money);
  		$payment_list = get_payment_list("wap");
		$GLOBALS['tmpl']->assign("payment_list",$payment_list);
		$GLOBALS['tmpl']->assign("coll",is_tg(true));
  		$GLOBALS['tmpl']->display("account_mortgate_pay.html");
  	}
 	public function mine_investor_status(){
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
  		$user_id=$GLOBALS['user_info']['id'];
  		$type=intval($_REQUEST['type']);
    	$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
			$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
   		$investor_list=$GLOBALS['db']->getAll("select invest.*,d.end_time,d.pay_end_time,d.begin_time,d.name as deal_name ,d.image as deal_image,d.id as deal_id" .
   				" from  ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."deal as d on d.id=invest.deal_id where  invest.type=$type and invest.user_id=$user_id order by invest.id desc limit $limit ");
    	$investor_list_num=$GLOBALS['db']->getOne("select count(*) from  ".DB_PREFIX."investment_list where  type=$type and user_id=$user_id  ");
  		$now_time=NOW_TIME;
 		if($type==0||$type==2||$type==1){
   			foreach($investor_list as $k=>$v){
   				if($type==1){
   					if($now_time>$v['end_time']){
						$investor_list[$k]['status']=2;
						$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=2 where id= ".$v['id']);
   					}
   				}
				if($v['investor_money_status']==0&&$now_time>$v['end_time']){
					$investor_list[$k]['investor_money_status']=2;
					$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set investor_money_status=2 where id= ".$v['id']);
   				}elseif($v['investor_money_status']==1&&$now_time>$v['pay_end_time']){
   					$investor_list[$k]['investor_money_status']=4;
					deal_invest_break($v['id']);   				
   				}
   			}
		} 
   		
   		$title='';
   		switch($type){
  			case 1:
  			$title='领投列表';
  			break;
  			case 2:
  			$title='跟投列表';
  			break;
  			case 0:
  			$title='询价列表';
  			break;
  		}
  		$page = new Page($investor_list_num,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
   		$GLOBALS['tmpl']->assign('type',$type);
   		$GLOBALS['tmpl']->assign('title',$title);
   		$GLOBALS['tmpl']->assign('investor_list',$investor_list);
   		$GLOBALS['tmpl']->assign("page_title","投资的项目");
  		$GLOBALS['tmpl']->display("account_mine_investor.html");
  	}
  	/*我的推荐列表*/
  	public function recommend(){
  		if(!$GLOBALS['user_info'])
  		{
  			showErr("",0,url_wap("user#login"));
  		}
  		$page_size = ACCOUNT_PAGE_SIZE;
  		$page = intval($_REQUEST['p']);
  		if($page==0)$page = 1;
  		$limit = (($page-1)*$page_size).",".$page_size;
  		$user_id=intval($GLOBALS['user_info']['id']);
  		if(app_conf('INVEST_STATUS') == 0)
		{
			$recommend_info=$GLOBALS['db']->getAll("SELECT * FROM ".DB_PREFIX."recommend WHERE user_id=".$user_id." ORDER BY create_time DESC limit $limit");
  			$recommend_count=$GLOBALS['db']->getOne("SELECT count(*) FROM ".DB_PREFIX."recommend WHERE user_id=".$user_id);
  		}
		elseif(app_conf('INVEST_STATUS') == 1)
		{
			$recommend_info=$GLOBALS['db']->getAll("SELECT * FROM ".DB_PREFIX."recommend WHERE user_id=".$user_id." and deal_type = 0 ORDER BY create_time DESC limit $limit");
  			$recommend_count=$GLOBALS['db']->getOne("SELECT count(*) FROM ".DB_PREFIX."recommend WHERE  deal_type = 0 and user_id=".$user_id);
  		}
		else
		{
			$recommend_info=$GLOBALS['db']->getAll("SELECT * FROM ".DB_PREFIX."recommend WHERE user_id=".$user_id." and deal_type = 1 ORDER BY create_time DESC limit $limit");
  			$recommend_count=$GLOBALS['db']->getOne("SELECT count(*) FROM ".DB_PREFIX."recommend WHERE deal_type = 1 and user_id=".$user_id);
  		}
  		$page = new Page($recommend_count,$page_size);   //初始化分页对象
  		$p  =  $page->show();
  		$GLOBALS['tmpl']->assign('pages',$p);
  		$GLOBALS['tmpl']->assign("recommend_info",$recommend_info);
  		$GLOBALS['tmpl']->assign("page_title","推荐的项目");
  		$GLOBALS['tmpl']->display("account_recommend.html");
  	}
  	//领投资格列表
  	public function get_leader_list(){
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
  		$deal_id=$_REQUEST['id'];
  		$deal=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id=$deal_id and user_id=".$GLOBALS['user_info']['id']);
   		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
			$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
  		$investor_list=$GLOBALS['db']->getAll("select invest.*,u.user_name,u.mobile,u.user_level from  ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."user as u on u.id=invest.user_id where invest.type=1 and invest.deal_id=$deal_id order by invest.id desc limit $limit ");
   		$investor_list_num=$GLOBALS['db']->getOne("select count(*) as num from  ".DB_PREFIX."investment_list where type=1 and deal_id=$deal_id order by id desc limit $limit ");
  		$now_time=NOW_TIME;
  		foreach($investor_list as $k=>$v){
  			if($v['status']==0&&$now_time>$deal['end_time']){
  				$investor_list[$k]['status']=2;
   					$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=2 where id= ".$v['id']);
  			}
  			$investor_list[$k]['cates']=unserialize($v['cates']);
  			$investor_list[$k]['user_icon'] =$GLOBALS['user_level'][$v['user_level']]['icon'];//用户等级图标
  		}
  		$page = new Page($investor_list_num,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('deal',$deal);
		$GLOBALS['tmpl']->assign('deal_id',$deal_id);
		$GLOBALS['tmpl']->assign('pages',$p);
   		$GLOBALS['tmpl']->assign('investor_list',$investor_list);
   		$GLOBALS['tmpl']->assign("page_title","申请列表");
  		$GLOBALS['tmpl']->display("account_leader_list.html");
  	}
  	public function add_leader_info(){
  		if(!$GLOBALS['user_info']){
  			app_redirect(url_wap("user#login"));
  		}
  		$id=intval($_REQUEST['id']);
  		$leader_info=$GLOBALS['db']->getRow("select invest.*,d.name as deal_name from ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."deal as d on d.id=invest.deal_id where invest.id=".$id);
  		if($GLOBALS['user_info']['id']!=$leader_info['user_id']){
  			showErr("该页面不存在",0,"");
  			return false;
  		}
  		$file=unserialize($leader_info['leader_moban']);
  		$GLOBALS['tmpl']->assign('file',$file);
  		$GLOBALS['tmpl']->assign('leader_info',$leader_info);
  		$GLOBALS['tmpl']->assign('title','上传领投信息');
  		$GLOBALS['tmpl']->display("account_add_leader_info.html");
  	}
  	//我的项目 跟投列表,领投列表，询价列表
  	public function get_investor_status(){
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		$parameter=array();	
  		$deal_id=$_REQUEST['id'];
  		$type=intval($_REQUEST['type']);
  		
  		$GLOBALS['tmpl']->assign('deal_id',$deal_id);

  		$deal=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id=$deal_id and user_id=".$GLOBALS['user_info']['id']);
  		$GLOBALS['tmpl']->assign('deal',$deal);
  	 	
  		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
			$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		if($type==1){
			$condition=" and  invest.type=1 and invest.deal_id=$deal_id and invest.money>0 and invest.status=1 ";
		}else{
			$condition=" and  invest.type=$type and invest.deal_id=$deal_id ";
		}
		
  		$investor_list=$GLOBALS['db']->getAll("select invest.*,u.user_name from  ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."user as u on invest.user_id=u.id where 1=1 $condition order by id desc limit $limit ");
   		$investor_list_num=$GLOBALS['db']->getOne("select count( invest.id) from  ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."user as u on invest.user_id=u.id where 1=1 $condition  ");
   		$now_time=NOW_TIME;
   		
 		if($type==0||$type==2||$type==1){
   			foreach($investor_list as $k=>$v){
   				if($type==1){
   					if($v['status']==0&&$now_time>$deal['end_time']){
 						$investor_list[$k]['status']=2;
						$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=2 where id= ".$v['id']);
   					}
   				}
    				if($v['investor_money_status']==0&&$now_time>$deal['end_time']){
    				$investor_list[$k]['investor_money_status']=2;
   					$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set investor_money_status=2 where id= ".$v['id']);
   				}elseif($v['investor_money_status']==1&&$now_time>$deal['pay_end_time']){
   					$investor_list[$k]['investor_money_status']=4;
					deal_invest_break($v['id']);
   				}
   			}
		} 
   		
   		$title='';
   		switch($type){
  			case 1:
  			$title='领投列表';
  			break;
  			case 2:
  			$title='跟投列表';
  			break;
  			case 0:
  			$title='询价列表';
  			break;
  		}
  		
  		$parameter_str="&".implode("&",$parameter);
  		$page = new Page($investor_list_num,$page_size,$parameter_str);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
   		$GLOBALS['tmpl']->assign('type',$type);
   		$GLOBALS['tmpl']->assign('deal_id',$deal_id);
   		$GLOBALS['tmpl']->assign('title',$title);
   		$GLOBALS['tmpl']->assign('investor_list',$investor_list);
   		$GLOBALS['tmpl']->assign("page_title","申请列表");
  		$GLOBALS['tmpl']->display("account_investor_list.html");
  	}
  	//提现
  	public function money_carry_bank(){
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));

		$banks=$GLOBALS['db']->getAll("select * from ".DB_PREFIX."user_bank where user_id=".$GLOBALS['user_info']['id']);
		$GLOBALS['tmpl']->assign('banks',$banks);
		if($GLOBALS['is_tg'] && $GLOBALS['user_info']['ips_acct_no']){
			//手续费
			$fee_config = load_auto_cache("user_carry_config");
			$json_fee = array();
			foreach($fee_config as $k=>$v){
				$json_fee[] = $v;
				if($v['fee_type']==1)
					$fee_config[$k]['fee_format'] = $v['fee']."%";
				else
					$fee_config[$k]['fee_format'] = format_price($v['fee']);
			}
			$GLOBALS['tmpl']->assign("fee_config",$fee_config);
			$GLOBALS['tmpl']->assign("json_fee",json_encode($json_fee));
		}
		$GLOBALS['tmpl']->assign("page_title","提现");
		$GLOBALS['tmpl']->display("account_money_carry_bank.html");
	}
	public function money_carry_log()
	{
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		$GLOBALS['tmpl']->display("account_money_carry_log.html");
	}
	public function money_carry_addbank()
	{
		$GLOBALS['tmpl']->assign("page_title","添加银行卡");
 		$bank_list=get_bank_list();
		$user_info=$GLOBALS['user_info'];
		if($user_info['investor_status'] != 1){
			showErr('您的身份认证未完成,请点击确定去实名认证!',1,url_wap("settings#security",array("method"=>"setting-id-box")));
		}
		$GLOBALS['tmpl']->assign('user_info',$GLOBALS['user_info']);
		
		$region_pid = 0;
		$region_lv2 = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."region_conf where region_level = 2 order by py asc");  //二级地址
		foreach($region_lv2 as $k=>$v)
		{
			if($v['name'] == $GLOBALS['user_info']['province'])
			{
				$region_lv2[$k]['selected'] = 1;
				$region_pid = $region_lv2[$k]['id'];
				break;
			}
		}
		$GLOBALS['tmpl']->assign("region_lv2",$region_lv2);
		if($region_pid>0)
		{
			$region_lv3 = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."region_conf where pid = ".$region_pid." order by py asc");  //三级地址
			foreach($region_lv3 as $k=>$v)
			{
				if($v['name'] == $GLOBALS['user_info']['city'])
				{
					$region_lv3[$k]['selected'] = 1;
					break;
				}
			}
			$GLOBALS['tmpl']->assign("region_lv3",$region_lv3);
		}
  		$GLOBALS['tmpl']->assign('bank_list',$bank_list);
		$GLOBALS['tmpl']->display("inc/account_money_carry_addbank.html");
	}
	public function addbank(){
		$GLOBALS['tmpl']->display("account_money_carry_addbank.html");
	}
	public function delbank(){
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		$ajax=1;
		$id=intval($_REQUEST['id']);
		$user_id=$GLOBALS['user_info']['id'];
		if($id>0){
			$re=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."user_bank where user_id=$user_id and id=$id ");
			if($re){
				$result= $GLOBALS['db']->query("delete from  ".DB_PREFIX."user_bank where id=$id");
				 if($result){
				 	showSuccess("删除成功" ,$ajax);
				 }else{
				 	showErr("删除失败",$ajax);
				 }
			}else{
				showErr("您没有权限删除该银行卡",$ajax);
			}
		}else{
			showErr("不存在该银行卡",$ajax);
		}
	}
	public function savebank(){
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
 		$ajax=intval($_REQUEST['ajax']);
		if(empty($GLOBALS['user_info']['identify_name'])){
			showErr('请进行完成身份认证，才可以添加银行卡',$ajax);
		}
 		$bank_id = strim($_REQUEST['bank_id']);
 		$otherbank=intval($_REQUEST['otherbank']);
 		$data=array();
 		if(empty($bank_id)){
			showErr("请选择银行".$bank_id,$ajax);
		}else{
			if($bank_id=='other'){
				if($otherbank==0){
					showErr("请选择银行",$ajax);
				}else{
					$data['bank_id']=$otherbank;
				}
			}else{
				$data['bank_id']=$bank_id;
			}
		}
		$province=strim($_REQUEST['province']);
		if(empty($province)){
			showErr("请选择省份",$ajax);
		}
		$data['region_lv2']=$province;
		$city=strim($_REQUEST['city']);
		if(empty($city)){
			showErr("请选择城市",$ajax);
		}
		$data['region_lv3']=$city;
		$bankzone=strim($_REQUEST['bankzone']);
		if($bankzone==''){
			showErr('请填写开户行网点',$ajax);
		}
		$data['bankzone']=$bankzone;
		$bankcard=strim($_REQUEST['bankcard']);
		if($bankcard==''){
			showErr('请填写银行卡号',$ajax);
		}
		if(strlen($_REQUEST['bankcard'])<12)
		{
			showErr("最少输入10位账号信息！",$ajax);
		}
		$data['bankcard']=$bankcard;
		$reBankcard=strim($_REQUEST['reBankcard']);
		if($reBankcard!=$bankcard){
			showErr('银行卡号与确认卡号不一致',$ajax);
		}
		$data['reBankcard']=$reBankcard;
		$data['user_id']=$GLOBALS['user_info']['id'];
		
 		$data['real_name']=$GLOBALS['user_info']['identify_name'];
 		$data['bank_name']=$GLOBALS['db']->getOneCached("select name from ".DB_PREFIX."bank where id=".$data['bank_id']);
 		$re=$GLOBALS['db']->autoExecute(DB_PREFIX."user_bank",$data,"INSERT","","SILENT");
 		if($re){
 			showSuccess("添加成功",$ajax,url_wap("account#money_carry_bank"));
 		}else{
 			showErr("插入失败",$ajax);
 		}
		
	}
	public function money_carry()
	{
		$user_bank_id=intval($_REQUEST['id']);
		$bank_info=$GLOBALS['db']->getRow("select * from ".DB_PREFIX."user_bank where id=".$user_bank_id." and user_id=".$GLOBALS['user_info']['id']);
 		if(!$bank_info){
 			showErr("银行信息不存在",0,url_wap("account#money_carry_bank"));
 		}
 		$ready_refund_money =doubleval($GLOBALS['db']->getOne("select sum(money) from ".DB_PREFIX."user_refund where user_id = ".intval($GLOBALS['user_info']['id'])." and is_pay = 0"));
 		$bank_info['can_use_money']=$GLOBALS['user_info']['money']-$ready_refund_money;
 		$bank_info['ready_refund_money']=$ready_refund_money;
 		$bank_info['bankcard']=substr($bank_info['bankcard'],0,-6)."***".substr($bank_info['bankcard'],-3);
 		$GLOBALS['tmpl']->assign('bank_info',$bank_info);
 		$GLOBALS['tmpl']->assign("page_title","提现到银行卡");
 		$GLOBALS['tmpl']->display("account_money_carry.html");
	}
	
	public function support()
	{	
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		$GLOBALS['tmpl']->assign("page_title","支持列表");
		
		$deal_id = intval($_REQUEST['id']);
		$type = intval($_REQUEST['type']);
		$user_name = strim($_REQUEST['user_name']);
		$mobile = strim($_REQUEST['mobile']);
		$repay_status = intval($_REQUEST['repay_status']);
		
		$parameter=array();
		$parameter[]="type=".$type;
		$parameter[]="id=".$deal_id;
		
		$deal_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id = ".$deal_id." and is_delete = 0 and is_effect = 1 and is_success = 1 and user_id = ".intval($GLOBALS['user_info']['id']));
		
		if(!$deal_info)
		{
			app_redirect_preview();
		}
		$GLOBALS['tmpl']->assign("deal_info",$deal_info);
		$where='';
		
		
		$page_size = 5;
		$page = intval($_REQUEST['p']);
		if($page==0)$page = 1;		
		$limit = (($page-1)*$page_size).",".$page_size;
		
		if($type ==1)
			$support_list = $GLOBALS['db']->getAll("select d.*,i.stock_value as investment_stock_value,i.type as invest_type from ".DB_PREFIX."deal_order as d "." left join ".DB_PREFIX."investment_list as i on i.id = d.invest_id  where d.deal_id = ".$deal_id." and d.order_status = 3 and d.is_refund = 0 and d.invest_id >0 ".$where." order by d.create_time desc limit ".$limit);
		else
			$support_list = $GLOBALS['db']->getAll("select d.*,di.description as item_description from ".DB_PREFIX."deal_order as d left join ".DB_PREFIX."deal_item as di on di.id=d.deal_item_id where d.deal_id = ".$deal_id." and d.order_status = 3 and d.is_refund = 0 and d.deal_item_id >0 ".$where." order by d.create_time desc limit ".$limit);
	
		$support_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal_order where deal_id = ".$deal_id." and order_status = 3 and is_refund = 0 ".$where." ");
		foreach($support_list as $k=>$v){
			if($deal_info['type']==1){
				//项目金额
				$support_list[$k]['stock_value'] =number_format($deal_info['limit_price'],2);
				$support_list[$k]['money'] =$v['total_price'];
				//用户所占股份
 				$support_list[$k]['user_stock']= number_format(($support_list[$k]['money']/$deal_info['limit_price'])*$deal_info['transfer_share'],2);
				
				if($v['invest_type'] ==0)
					$invest_type_val='询价';
				elseif($v['invest_type'] ==1)
					$invest_type_val='领投';
				elseif($v['invest_type'] ==2)
					$invest_type_val='跟投';
				elseif($v['invest_type'] ==2)
					$invest_type_val='追加';
					
				$support_list[$k]['invest_type_val']=$invest_type_val;
				
			}
		}
		$GLOBALS['tmpl']->assign("support_list",$support_list);
		$parameter_str="&".implode("&",$parameter);
		$page = new Page($support_count,$page_size,$parameter_str);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);	
		
		$GLOBALS['tmpl']->assign('deal_id',$deal_id);
		$GLOBALS['tmpl']->assign('type',$type);
		$GLOBALS['tmpl']->assign('user_name',$user_name);
		$GLOBALS['tmpl']->assign('mobile',$mobile);
		$GLOBALS['tmpl']->assign('repay_status',$repay_status);
		$GLOBALS['tmpl']->display("account_support.html");
	}
	public function set_repay(){
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		$GLOBALS['tmpl']->assign("page_title","发放回报");
		$order_id= intval($_REQUEST['id']);
	
		
		if($_REQUEST['type'] ==1)
			$order_info = $GLOBALS['db']->getRow("select d.*,i.stock_value as investment_stock_value,dl.transfer_share as transfer_share,dl.limit_price as limit_price from ".DB_PREFIX."deal_order as d left join (".DB_PREFIX."investment_list as i,".DB_PREFIX."deal as dl) on (i.id = d.invest_id and d.deal_id = dl.id)  where d.id = ".$order_id." and d.order_status = 3 and d.is_refund = 0 and d.invest_id >0 order by d.create_time desc ");
		else
			$order_info = $GLOBALS['db']->getRow("select d.*,di.description as item_description from ".DB_PREFIX."deal_order as d left join ".DB_PREFIX."deal_item as di on di.id=d.deal_item_id where d.id = ".$order_id." and d.order_status = 3 and d.is_refund = 0 and d.deal_item_id >0 order by d.create_time desc");
	

		if($order_info['type']==1){
			//用户所占股份
			$order_info['user_stock']= number_format(($order_info['total_price']/$order_info['limit_price'])*$order_info['transfer_share'],2);			
			//项目金额
			$order_info['stock_value'] =number_format($order_info['limit_price']/10000,2);
			//应付金额
			$order_info['total_price'] =number_format($order_info['total_price']/10000,2);
		}
		$GLOBALS['tmpl']->assign("order_info",$order_info);
		$GLOBALS['tmpl']->display("account_set_repay.html");
	}
	public function save_repay()
	{
		$ajax = intval($_REQUEST['ajax']);
		if(!$GLOBALS['user_info'])
		{
			showErr("",$ajax,url_wap("user#login"));
		}
		
		$order_id = intval($_REQUEST['id']);
		$order_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal_order where id = ".$order_id." and order_status = 3 and is_refund = 0");
		if(!$order_info)
		{
			showErr("无权为该订单设置回报",$ajax);
		}
		
		$deal_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id = ".$order_info['deal_id']." and is_delete = 0 and is_effect = 1 and is_success = 1 and user_id = ".intval($GLOBALS['user_info']['id']));
		if(!$deal_info)
		{
			showErr("无权为该订单设置回报",$ajax);
		}
		
		$order_info['repay_time'] = NOW_TIME;
		$order_info['repay_memo'] = strim($_REQUEST['repay_memo']);
		
		if($order_info['repay_memo']=="")
		{
			showErr("请输入回报内容",$ajax);
		}
		$order_info['logistics_company'] = strim($_REQUEST['logistics_company']);
		$order_info['logistics_links'] = strim($_REQUEST['logistics_links']);
		$order_info['logistics_number'] = strim($_REQUEST['logistics_number']);
		$GLOBALS['db']->autoExecute(DB_PREFIX."deal_order",$order_info,"UPDATE","id=".$order_info['id']);
		if($GLOBALS['db']->affected_rows()>0)
		{
			if($order_info['share_fee']>0 && $order_info['share_status'] ==0 )
			{
				require_once APP_ROOT_PATH."system/libs/user.php";
				$add_param=array("type"=>3,"deal_id"=>$order_info['deal_id']);
				
				modify_account(array("money"=>$order_info['share_fee']),intval($order_info['user_id']),$order_info['deal_name']."项目成功，(订单:".$order_info['id'].")回报所得分红。",$add_param);						
				$GLOBALS['db']->query("update ".DB_PREFIX."deal_order set share_status=1 where id=".intval($order_info['id'])." and share_status=0");
			}
			//send_notify($order_info['user_id'],"您支持的项目".$order_info['deal_name']."回报已发放","account#view_order","id=".$order_info['id']);
			$param = array(
				'content'=>"您支持的项目".$order_info['deal_name']."回报已发放",
				'url_route'=>"account#view_order",
				'url_param'=>"id=".$order_info['id']
			);
			$GLOBALS['msg']->manage_msg('notify',$order_info['user_id'],$param);
			if($order_info['type']==1){
				showSuccess("回报设置成功",$ajax,url_wap("account#support",array("id"=>$order_info['deal_id'],"type"=>1)));
			}
			else{
				showSuccess("回报设置成功",$ajax,url_wap("account#support",array("id"=>$order_info['deal_id'])));
			}
		}else
		{
			showErr("回报设置失败",$ajax);
		}
		
		
	}
	//我的项目 放款记录
	public function paid()
	{
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		
		$deal_id = intval($_REQUEST['id']);
		$deal_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal where id = ".$deal_id." and is_delete = 0 and is_effect = 1 and is_success = 1 and user_id = ".intval($GLOBALS['user_info']['id']));
		
		if(!$deal_info)
		{
			app_redirect_preview();
		}
		$GLOBALS['tmpl']->assign("deal_info",$deal_info);
		
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)$page = 1;		
		$limit = (($page-1)*$page_size).",".$page_size	;

		$paid_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."deal_pay_log where deal_id = ".$deal_id." order by create_time desc limit ".$limit);
		$paid_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."deal_pay_log where deal_id = ".$deal_id);
		
		
		$GLOBALS['tmpl']->assign("paid_list",$paid_list);

		$page = new Page($paid_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign("page_title","发放记录");
		$GLOBALS['tmpl']->display("account_paid.html");
	}
	public function investor_examine(){
  		if(!$GLOBALS['user_info'])
		app_redirect(url("user#login"));
		
  		$ajax=1;
  		//$result=array('status'=>1,'info'=>'');
  		$id=intval($_REQUEST['id']);
  		$type=intval($_REQUEST['type']);
  		$status=intval($_REQUEST['status']);
  		$item=$GLOBALS['db']->getRow("select * from  ".DB_PREFIX."investment_list where id=$id ");
  		if(!$item){
  			showErr("该询价不存在",$ajax,"");
  		}
  		$deal_id=intval($item['deal_id']);
  		$deal=$GLOBALS['db']->getRow("select * from  ".DB_PREFIX."deal where id=$deal_id and user_id=".$GLOBALS['user_info']['id']);
  		if(!$deal){
  			showErr("项目不存在",$ajax,"");
  		}
  		if($status==1){
  			$now_money=$GLOBALS['db']->getOne("select sum(money) from ".DB_PREFIX."investment_list where deal_id=$deal_id and (investor_money_status=1 or investor_money_status=3  )");
  			if($type==0){
  				if($item['stock_value']<($now_money+$item['money'])){
	  				showErr("您允许的金额已超过估值的额度".$deal['stock_value'],$ajax);
	  			}
  			}else{
  				if($deal['limit_price']<($now_money+$item['money'])){
	  				showErr("您允许的金额已超过你的融资额度".$deal['limit_price'],$ajax);
	  			}
  			}
  			
  			$this->create_investor_pay($id);
  			$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set investor_money_status=1 where id=$id ");
   			if($type==0){
   				$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."deal set limit_price=".$item['stock_value']." where id=$deal_id ");
   			}
   			
   		}else{
   			$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set investor_money_status=2 where id=$id ");
   		}
  		showSuccess("审核成功",$ajax);
  		
  		
  	}
  	public function create_investor_pay($invest_id){
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
  		
  		$ajax=1;
  		if(!$invest_id){
  			showErr("该申请错误",$ajax);
  		}
  		$invest=$GLOBALS['db']->getRow("select  invest.*,u.user_name from  ".DB_PREFIX."investment_list as invest left join ".DB_PREFIX."user as u on u.id=invest.user_id where invest.id=".$invest_id."  ");
  		if(!$invest){
  			showErr("没有该申请",$ajax);
  		}
  		$user_id=$GLOBALS['user_info']['id'];
  		$deal_info=$GLOBALS['db']->getRow("select * from  ".DB_PREFIX."deal where id=".$invest['deal_id']." and user_id=$user_id");
  		
  		if(!$deal_info){
  			showErr("没有该项目",$ajax);
  		}
  		$order_info['deal_id'] = $deal_info['id'];
 		$order_info['user_id'] = intval($invest['user_id']);
		$order_info['user_name'] = $invest['user_name'];
		$order_info['total_price'] = $invest['money'];
		$order_info['delivery_fee'] = 0;
		$order_info['deal_price'] = $invest['money'];
		$order_info['support_memo'] = "";
		//$order_info['payment_id'] = "";
		//$order_info['bank_id'] = strim($_REQUEST['bank_id']);
		
 		
		$order_info['credit_pay'] = 0;
		$order_info['online_pay'] = 0;
		$order_info['deal_name'] = $deal_info['name'];
		$order_info['order_status'] = 0;
		$order_info['type'] = 1;
		$order_info['invest_id'] = $invest_id;
		$order_info['create_time']	= NOW_TIME;
		$order_info['is_success'] = $deal_info['is_success'];
		$GLOBALS['db']->autoExecute(DB_PREFIX."deal_order",$order_info);
		
		$order_id = $GLOBALS['db']->insert_id();
		if(!$order_id){
			showErr("订单生成失败",$ajax);
		}else{
			//生成发送通知
			invest_pay_send($invest['id'],$order_id);
		}
		$GLOBALS['db']->query("UPDATE ".DB_PREFIX."investment_list SET order_id=$order_id where id=".$invest['id']);
  	}
  	public function lead_examine(){
  		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
  		
  		$ajax=1;
  		$id=intval($_REQUEST['id']);
  		$type=intval($_REQUEST['type']);
  		$status=intval($_REQUEST['status']);
  		$item=$GLOBALS['db']->getRow("select * from  ".DB_PREFIX."investment_list where id=$id and type=1 ");
  		if(!$item){
  			showErr("该申请不存在",$ajax,"");
  		}
  		if($status==1){
  			$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=1 where id=$id ");
  			$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=2 where deal_id=".$item['deal_id']." and type=1 and id!=".$item['id']);
  		}else{
   			$GLOBALS['db']->query("UPDATE  ".DB_PREFIX."investment_list set status=2 where id=$id ");
  		}
  		showSuccess("审核成功",$ajax);
  	}
  	public function money_freeze()
	{
       
		$GLOBALS['tmpl']->assign("page_title","诚意金明细");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$condition =" and amount <>0";

		$money_freeze_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."money_freeze where platformUserNo = ".intval($GLOBALS['user_info']['id'])."   $condition order by create_time desc,id desc limit ".$limit);
 		foreach($money_freeze_list as $k=>$v)
		{
			$money_freeze_list[$k]['deal_name']= $GLOBALS['db']->getOne("select name from ".DB_PREFIX."deal where id=".$v['deal_id']);
		}
 		$money_freeze_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."money_freeze where platformUserNo = ".intval($GLOBALS['user_info']['id'])."  $condition ");
 		  		
 		$page = new Page($money_freeze_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('money_freeze_list',$money_freeze_list);
		
		$GLOBALS['tmpl']->display("account_money_freeze.html");
	}
	public function set_money_unfreeze(){
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}
		
		$money_unfreeze_id= intval($_REQUEST['id']);
		$GLOBALS['db']->query("update ".DB_PREFIX."money_freeze set status = 3,create_time=".NOW_TIME." where id = ".$money_unfreeze_id );

 		showSuccess("申请解冻成功",1,url_wap("account#money_freeze"));		
	}
	public function set_money_freeze(){
		if(!$GLOBALS['user_info'])
		{
			app_redirect(url_wap("user#login"));
		}

		$money_freeze_id= intval($_REQUEST['id']);
		$GLOBALS['db']->query("update ".DB_PREFIX."money_freeze set status = 1,create_time=".NOW_TIME." where id = ".$money_freeze_id );

 		showSuccess("取消申请成功",1,url_wap("account#money_freeze"));
	
	}
	public function yeepay_recharge()
	{
       
		$GLOBALS['tmpl']->assign("page_title","充值记录");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$condition =" and amount <>0";
		
		$yeepay_recharge_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."yeepay_recharge where platformUserNo = ".intval($GLOBALS['user_info']['id'])." $condition order by create_time desc,id desc limit ".$limit);
 		
 		$yeepay_recharge_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."money_freeze where platformUserNo = ".intval($GLOBALS['user_info']['id'])."  $condition ");
 		 		
 		$page = new Page($yeepay_recharge_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('yeepay_recharge_list',$yeepay_recharge_list);
		
		$GLOBALS['tmpl']->display("account_yeepay_recharge.html");
	}
	public function yeepay_withdraw()
	{
       
		$GLOBALS['tmpl']->assign("page_title","提现记录");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$condition =" and amount <>0";
		$yeepay_withdraw_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."yeepay_withdraw where platformUserNo = ".intval($GLOBALS['user_info']['id'])." $condition order by create_time desc,id desc limit ".$limit);
 		
 		$yeepay_withdraw_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."yeepay_withdraw where platformUserNo = ".intval($GLOBALS['user_info']['id'])."  $condition ");
	
 		$page = new Page($yeepay_withdraw_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('yeepay_withdraw_list',$yeepay_withdraw_list);
		
		$GLOBALS['tmpl']->display("account_yeepay_withdraw.html");
	}
	public function yeepay_money_freeze()
	{
       
		$GLOBALS['tmpl']->assign("page_title","诚意金明细");
		if(!$GLOBALS['user_info'])
		app_redirect(url_wap("user#login"));
		
		$page_size = ACCOUNT_PAGE_SIZE;
		$page = intval($_REQUEST['p']);
		if($page==0)
		$page = 1;
		$limit = (($page-1)*$page_size).",".$page_size;
		
		$condition =" and amount <>0";		
		$yeepay_money_freeze_list = $GLOBALS['db']->getAll("select * from ".DB_PREFIX."money_freeze where platformUserNo = ".intval($GLOBALS['user_info']['id'])." and pay_type = 0 $condition order by create_time desc,id desc limit ".$limit);
 		foreach($yeepay_money_freeze_list as $k=>$v)
		{
			$yeepay_money_freeze_list[$k]['deal_name']= $GLOBALS['db']->getOne("select name from ".DB_PREFIX."deal where id=".$v['deal_id']);
		}
 		$yeepay_money_freeze_count = $GLOBALS['db']->getOne("select count(*) from ".DB_PREFIX."money_freeze where platformUserNo = ".intval($GLOBALS['user_info']['id'])." and pay_type = 0  $condition ");		 
 		
 		$page = new Page($yeepay_money_freeze_count,$page_size);   //初始化分页对象 		
		$p  =  $page->show();
		$GLOBALS['tmpl']->assign('pages',$p);
		$GLOBALS['tmpl']->assign('yeepay_money_freeze_list',$yeepay_money_freeze_list);
		
		$GLOBALS['tmpl']->display("account_yeepay_money_freeze.html");
	}
 }
?>