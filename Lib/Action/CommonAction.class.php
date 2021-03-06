<?php
class CommonAction extends Action {
	public $_sid = '';		// session id
	public $_user = array();	// 全局 user
	
	// header 相关
	public $_title = array();	// header.htm title
	public $_nav = array();		// header.htm 导航
	public $seo_keywords = '';	// header.htm keywords
	public $seo_description = '';	// header.htm description
	public $_checked = array();	// 选中状态
	
	function _initialize() {
		import("@.ORG.utf8");
		import("@.ORG.Image");
		import("@.ORG.misc");
		import("@.ORG.Page");
		import("@.ORG.Video");
		import("@.ORG.Video");
		import("@.ORG.Video");
		import("@.ORG.simple_html_dom");
		import("@.ORG.Snoopy");
		
		//add at 20131119 by littlebear
		$this->collect = D('Collect');
		$this->collect_match = D('Collectmatch');
		$this->weixin = D('Weixin');
		
		$this->init_view();
		$this->init_sid();
		$this->init_user();
		//$this->bannedip();
	}
	
	function _empty(){
		header("Location: /Tpl/404.htm");
	}

	private function init_view() {
		$app_url = C('APP_URL');
		$mversion = C('MVERSION');
		$this->assign('app_url', $app_url);
		$this->assign('mversion', $mversion);
		$this->assign('_title', $this->_title);
		$this->assign('_nav', $this->_nav);
		$this->assign('seo_keywords', $this->seo_keywords);
		$this->assign('seo_description', $this->seo_description);
		$this->assign('_checked', $this->_checked);
		$this->assign('_user', $this->_user);
	}
	
	// 初始化 sid
	private function init_sid() {
		$sid_pre = C('COOKIE_PREFIX').'sid';
		if(empty($_COOKIE[$sid_pre])) {
			$sid = substr(md5($_SERVER['REMOTE_ADDR']).rand(1, 2147483647), 0, 16); // 兼容32,64位
			$_SERVER['time'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
			set_cookie(C('COOKIE_PREFIX').'sid', $sid, $_SERVER['time'] + 86400 * 30, '/');
		} else {
			$sid = $_COOKIE[$sid_pre];
		}
		$this->_sid = $sid;
		$this->assign('_sid', $this->_sid);		
		define('FORM_HASH', form_hash(C('PUBLIC_KEY')));
	}
	
	private function init_user() {
		$cookie = C('COOKIE_PREFIX').'auth';
		$xn_auth = isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie]: NULL;
		$this->_user = $this->decrypt_auth($xn_auth);
		$this->assign('_auth', $xn_auth);
		if($this->_user['uid']) {
			$this->_user = $this->user->where("uid=".$this->_user['uid'])->find();
			$smallavatar = 'upload/avatar/'.image::get_dir($this->_user['uid']).'/'.$this->_user['uid'].'_avatar_small.jpg';				
			$this->_user['smallavatar'] = file_exists($smallavatar) ? $smallavatar : './Tpl/image/user_icon30.jpg';		
		}				
		$this->assign('_user', $this->_user);
		//$this->bannedip();
		$this->useraction();
	}
	
	protected function check_user_exists($user) {
		if(empty($user)) {
			$this->message('用户不存在！可能已经被删除。', 0);
		}
	}	
	
	protected function check_forum_exists($forum) {
		if(empty($forum)) {
			$this->message('精选社不存在！可能被设置了隐藏。', 0);
		}
	}
	
	public function authcode($string, $operation = 'ENCODE', $key = 'randomstring') {
		$coded = '';
		$keylength = strlen($key);
		$string = $operation == 'DECODE' ? base64_decode($string) : $string;
		for($i = 0; $i < strlen($string); $i += $keylength) {
			$coded .= substr($string, $i, $keylength) ^ $key;
		}
		$coded = $operation == 'ENCODE' ? str_replace('=', '', base64_encode($coded)) : $coded;
		return $coded;
	}
	
	public function decrypt_auth($xn_auth) {
		$key = md5(C('PUBLIC_KEY'));		
		$s = decrypt($xn_auth, $key);	
		$return =  array('uid'=>0, 'username'=>'', 'groupid'=>C('GROUPID_GUEST'), 'password'=>'', 'ip_right'=>FALSE);
		if(!$s) {
			return $return;
		}
		$arr = explode("\t", $s);
		if(count($arr) < 5) {
			return $return;
		}
		$return = array (
			'uid'=>$arr[0],
			'username'=>$arr[1],
			'groupid'=>$arr[2],
			'password'=>$arr[3],
			'ip_right'=>$_SERVER['REMOTE_ADDR'] == $arr[4],
		);
		return $return;
	}
	
	// 格式化帖子内容 截取 所在精选社 时间等
	public function thread_format(&$thread) {
		$thread['dateline'] = $this->format_date($thread['dateline']);
		$thread['lastuptime'] = $this->format_date($thread['lastuptime']);
		$thread['subsubject'] = utf8::substr($thread['subject'], 0, 10);
	}

	// 格式化精选社信息 时间 icon等
	
	public function forum_format(&$forum) {
		if(!empty($forum['fid'])) {
			$forum['icon'] = 'upload/forum_icon/'.image::get_dir($forum['fid']).'/'.$forum['fid'].'_icon_big.jpg?'.time();
			$forum['middleicon'] = 'upload/forum_icon/'.image::get_dir($forum['fid']).'/'.$forum['fid'].'_icon_middle.jpg?'.time();
		}
		if(!file_exists($forum['middleicon'])) {
			$forum['icon'] = './Tpl/image/icon.jpg';	
			$forum['middleicon'] = './Tpl/image/icon.jpg';	
		}
	}
	
	
	public function format_date($time, $format = 'Y-n-j H:i', $timeoffset = '+8') {
		return gmdate($format, $time + $timeoffset * 3600);
	}
	
	public function feeds_format(&$feed) {
		$date = array();			
		if(!empty($feed)) {
			$clicknum = 0 ;	
			$feed['dateline'] = date("m月d日 H:i", $feed['dateline']);
			$avatar_src = 'upload/avatar/'.image::get_dir($feed['uid']).'/'.$feed['uid'].'_avatar_middle.jpg';				
			$feed['avatar'] = file_exists($avatar_src) ? $avatar_src : './Tpl/image/user_icon50.jpg';
			if(!empty($feed['fid'])) {
				$foruminfo = $this->forum->where("fid = $feed[fid]")->find();
				if(!empty($foruminfo)) {
					$feed['forumname'] = $foruminfo['name'];
				}
			}
						
			$app_url = C("APP_URL");				
			switch($feed['type']) {
				case 'post':	
						$threadinfo = $this->thread->where("fid = $feed[fid] and tid = $feed[tid]")->find();						
						if(!empty($threadinfo)) {
							$feed['username'] = $threadinfo['username'];
							$feed['subject'] = utf8::substr($threadinfo['subject'], 0, 18);
							$img1 = 'upload/attach/'.$threadinfo['coversrc1'];
							$feed['coversrc1'] =  file_exists($img1)&&!empty($threadinfo['coversrc1']) ? $app_url.'upload/attach/'.$threadinfo['coversrc1'] : '';
							$feed['coversrc2'] = $threadinfo['coversrc2'];
							$feed['coversrc3'] = $threadinfo['coversrc3'];
							$feed['summary'] = utf8::substr($threadinfo['summary'], 0, 100);
							$feed['views'] = $threadinfo['views'];
							$feed['posts'] = $threadinfo['posts'];
							$feed['astars'] = $threadinfo['astars'];									
						} 
					break;
				case 'message':
						if(!empty($feed['feedid'])) {
							$messageinfo = $this->forummessage->where("mid = $feed[feedid]")->find();
							if(!empty($messageinfo)) {
								//$feed['message'] = utf8::substr($messageinfo['message'], 0, 20);
								$feed['message'] = $messageinfo['message'];
							}
						}
					break;	
				case 'reply':
						$threadinfo = $this->thread->where("fid = $feed[fid] and tid = $feed[tid]")->find();	
						if(!empty($threadinfo)) {							
							$feed['authoruid'] = $threadinfo['uid'];
							$feed['author'] = $threadinfo['username'];
							$authoravatar_src = 'upload/avatar/'.image::get_dir($threadinfo['uid']).'/'.$threadinfo['uid'].'_avatar_middle.jpg';
							$feed['authoravatar'] = file_exists($authoravatar_src) ? $authoravatar_src : './Tpl/image/user_icon50.jpg';
							$feed['subject'] = utf8::substr($threadinfo['subject'], 0, 18);
							$img1 = 'upload/attach/'.$threadinfo['coversrc1'];
							$feed['coversrc1'] =  file_exists($img1)&&!empty($threadinfo['coversrc1']) ? $app_url.'upload/attach/'.$threadinfo['coversrc1'] : '';
							$feed['coversrc2'] = $threadinfo['coversrc2'];
							$feed['coversrc3'] = $threadinfo['coversrc3'];
							$feed['summary'] = utf8::substr($threadinfo['summary'], 0, 100);
							$feed['views'] = $threadinfo['views'];
							$feed['posts'] = $threadinfo['posts'];
							$feed['astars'] = $threadinfo['astars'];
							$threadreply = $this->threadreply->where("fid = $feed[fid] and tid = $feed[tid] and uid = $feed[uid]")->order("replyid DESC")->limit(0,1)->select();						
							$feed['replyinfo'] = array();
							if(!empty($threadreply)) {
								foreach($threadreply as &$rep) {
									$rep['message'] = utf8::substr($rep['message'], 0, 80);
									$avatar_src = 'upload/avatar/'.image::get_dir($rep['uid']).'/'.$rep['uid'].'_avatar_middle.jpg';	
									$rep['avatar'] = file_exists($avatar_src) ? $avatar_src : 'Tpl/image/user_icon50.jpg';
									$rep['dateline'] = date("m月d日 H:i", $rep['dateline']);
									array_push($feed['replyinfo'], $rep);
								}
							}
						} 						
					break;
				case 'threadgrade':						
						$threadinfo = $this->thread->where("fid = $feed[fid] and tid = $feed[tid]")->find();	
						if(!empty($threadinfo)) {
							$feed['subject'] = utf8::substr($threadinfo['subject'], 0, 18);
							$feed['authoruid'] = $threadinfo['uid'];
							$feed['author'] = $threadinfo['username'];
							$authoravatar_src = 'upload/avatar/'.image::get_dir($threadinfo['uid']).'/'.$threadinfo['uid'].'_avatar_middle.jpg';
							$feed['authoravatar'] = file_exists($authoravatar_src) ? $authoravatar_src : './Tpl/image/user_icon50.jpg';
							$img1 = 'upload/attach/'.$threadinfo['coversrc1'];
							$feed['coversrc1'] =  file_exists($img1)&&!empty($threadinfo['coversrc1']) ? $app_url.'upload/attach/'.$threadinfo['coversrc1'] : '';
							$feed['coversrc2'] = $threadinfo['coversrc2'];
							$feed['coversrc3'] = $threadinfo['coversrc3'];
							$feed['summary'] = utf8::substr($threadinfo['summary'], 0, 100);
							$feed['views'] = $threadinfo['views'];
							$feed['posts'] = $threadinfo['posts'];
							$feed['astars'] = $threadinfo['astars'];						
						} 
						
					break;
				case 'stopic':
						$feed['typestr'] = '专题';											
						//$feed['dateline'] = date("Y-m-d", $feed['dateline']);
						$icon_src = 'upload/stopic_banner/'.image::get_dir($feed['feedid']).'/'.$feed['feedid'].'_icon.jpg';				
						$icon = file_exists($icon_src) ? $icon_src : 'Tpl/image/350_235.jpg';
						$stopic = $this->stopic->where("stopicid = '".$feed['feedid']."'")->find();
						
						if(!empty($stopic)) {
							$feed['stopicid'] = $stopic['stopicid'];
							$feed['replys'] = $stopic['replys'];
							$feed['stopic_name'] = utf8::substr($stopic['name'], 0, 17);
							$feed['stopic_description'] = utf8::substr($stopic['description'], 0, 100);
						}											
					break;		
				case 'addforum':								
					break;		
				case 'upgrade':						
					break;				
				default:
								
			}
			return $feed;	
		} else {
			return array();
		}		
	}
	
	public function get_user($uid) {
		$userinfo = $this->user->where("uid = $uid")->find();
		if(!empty($userinfo)) {
			$avatar_src = 'upload/avatar/'.image::get_dir($uid).'/'.$uid.'_avatar_big.jpg';				
			$avatar = file_exists($avatar_src) ? $avatar_src : './Tpl/image/user_icon50.jpg';
			$user_sex = C('USER_SEX');
			$sex = $user_sex[$userinfo['gender']];
			$user_stage = C('USER_STAGE');	
			$stage = $user_stage[$userinfo['stage']];
			
			$provice = '北京';
			if(!empty($userinfo['province'])) {
				$proviceinfo = $this->province->where("provinceid = $userinfo[province]")->find();
				$provice = !empty($proviceinfo) ? $proviceinfo['provincename']: '北京';
			}
			
			$city = $userindustry= '';
			if(!empty($userinfo['city'])) {
				$cityinfo = $this->city->where("cityid = $userinfo[city]")->find();
				$city = !empty($cityinfo) ? $cityinfo['cityname']: '';
			}
			
			$industry = array();
			include MY_PATH."Conf/industry.php";
			if(!empty($userinfo['industry'])) {
				$userindustry = $industry[$userinfo['industry']];		
			}
			
			
			//用户加入的精选社信息						
			$forumsinfo = $this->forumuser->where("uid = $uid")->select();		
			$invests = $threads = $posts = $astars5 = $allastars5 = 0;					
			if(!empty($forumsinfo)) {			
				foreach($forumsinfo as $f) {
					$foruminfo = $this->forumuser->where("fid = $f[fid] and uid = $uid")->select();					
					if(!empty($foruminfo)) {						
						$invests += $f['invests'];
						$threads += $f['threads'];
						$posts += $f['posts'];												
					}
				}						
			}
			
			//用户在所有园子下的精品数
			$userastars5 = $this->thread->where("uid=$uid AND astars=5")->count();
			$astars5 = !empty($userastars5) ? $userastars5:0 ;
			
			//按uid查看回复
			$forumnum = !empty($forumsinfo) ? count($forumsinfo):0 ;		
			$userarr = array(
				'avatar'=>$avatar,
				'username'=>$userinfo['username'],
				'gold'=>$userinfo['credits1'],
				'sex'=>$sex,
				'stage'=>$stage,
				'signature'=>$userinfo['signature'],
				'provice'=>$provice,
				'city'=>$city,
				'industry'=>$userindustry,
				'forumnum'=>$forumnum,
				'follows'=>$userinfo['follows'],
				'fans'=>$userinfo['fans'],
				'invests'=>$invests,
				'threads'=>$threads,
				'posts'=>$posts,
				'astars5'=>$astars5,
			);					
			return $userarr;
		} else {
			return false;
		}			
	}
	
	
	public function checklogin() {
		header("Content-type: text/html; charset=utf-8"); 
		if(!empty($this->_user['uid'])) {
			return true;
		} else {
			 //$this->redirect('?m=index&a=urljump', '', 3, '未登录，请先登录后再操作!<br/>系统将于3秒后返回重新登陆...'); 
			 header("Location:http://www.kaoder.com/?index.htm");
		}
	}
	
	
	public function message($message, $status = 1, $goto = '') {	
		if($this->_get('ajax')) {
			// 可能为窗口，也可能不为。
			$json = array('servererror'=>'', 'status'=>$status, 'message'=>$message);
			echo json_encode($json);
			exit;
		} else {
			$this->assign('message', $message);
			$this->assign('status', $status);
			$this->assign('goto', $goto);
			$this->display('Tpl/message.htm');
			exit;
		}
	}
	
	public function replace_badword(&$msg) {
		if(!isset($_SERVER['badword'])) {
			$_SERVER['badword'] = @include WWW_PATH.'Conf/badword.php';
		}
		if(!empty($_SERVER['badword'])) {
			foreach($_SERVER['badword'] as $k=>&$v) {
				$strlen = mb_strlen($k);
				$star = '';
				for ($len = 1; $len <= $strlen; $len++) {
					$star .= "*";
				}
				$v = $star;
			}
			$keys = array_keys($_SERVER['badword']);
			$values = array_values($_SERVER['badword']);
			$msg = str_replace($keys, $values, $msg);
		}
		return $msg;
	}
	
	public function search_by_forum ($keyword, $fid, $astars, $start, $pagesize) {
		//$fid = intval($fid);
		//$astars = intval($astars);
		//include FRAMEWORK_PATH.'lib/sphinxapi.class.php';
		$cl = new SphinxClient ();
		$host = '10.0.0.248';
		$port = '9312';
		$cl->SetServer( $host, $port);
		$cl->SetConnectTimeout (3);
		$cl->SetArrayResult( true );
		$cl->SetWeights(array(100,1));
		//匹配所有查询词(默认模式)
		$cl->SetMatchMode(SPH_MATCH_ALL);
		//$cl->SetRankingMode ( SPH_RANK_PROXIMITY_BM25);
		//匹配查询词中的任意一个
		//$cl->SetMatchMode(SPH_MATCH_ANY);
		//将整个查询看作一个词组，要求按顺序完整匹配
		//$cl->SetMatchMode(SPH_MATCH_PHRASE);
		//$cl->SetSortMode(SPH_SORT_EXTENDED, 'fid desc');
		//将查询看作一个Sphinx/Coreseek内部查询语言的表达式
		//$cl->SetMatchMode(SPH_MATCH_EXTENDED2);
		$cl->SetSortMode(SPH_SORT_EXTENDED, 'dateline desc');
		//$cl->SetFilter('fid', array(2), false );
		
		if($astars !='default') {
			$cl->SetFilter('fid', array($fid), false );
			$cl->SetFilter ('astars', array($astars), false );
			//$cl->SetFilter ('isindex', array(0), false );
		} else {
			$cl->SetFilter('fid', array($fid), false );
		}
		//print_r(123);exit;
		$cl->SetLimits($start, $pagesize, ($pagesize>1000) ? $pagesize :1000);
		
		$res = $cl->Query($keyword, "kaoder_thread" );
		
		return $res;
	}
	
	public function decrypt_cookie($str) {
		$cookiearr = array();
		$ipstr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip = !empty($ipstr) ? ip2long($ipstr) : 0;		
		$public_key = C('PUBLIC_KEY');
		$key = md5($public_key);
		
		$destr = decrypt($str, $key);
		if(!empty($destr)) {
			$arr = explode("-", $destr);
			if(count($arr) < 4) {
				return $cookiearr;
			} else {
				return array('cookiestr'=>'kaoder','clicknum'=>$arr[1],'time'=>$arr[2],'ip'=>$arr[3]);
			}
		} else {
			return $cookiearr;
		}							
	}
	
	public function encrypt_cookie($cookiearr) {
		$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		$ipstr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip = !empty($ipstr) ? ip2long($ipstr) : 0;
		$public_key = C('PUBLIC_KEY');
		$key = md5($public_key);
		$cookieview = '';
		if(empty($cookiearr)) {
			return '';
		} else {
			$cookieview = encrypt("kaoder-$cookiearr[clicknum]-$cookiearr[time]-$cookiearr[ip]", $key);			
			misc::set_cookie('useraction', $cookieview,  $time+ 3600, '/', TRUE);
			return $cookieview;
		}
	}
	
	public function encrypt_form($uid) {
		$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		$ipstr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip = !empty($ipstr) ? ip2long($ipstr) : 0;
		$public_key = C('PUBLIC_KEY');
		$key = md5($public_key);
		if(empty($uid)) {
			return '';
		} else {
			return encrypt("$uid-$time-$ip", $key);			
		}
	}
	
	public function decrypt_form($str) {
		$arr = array();
		$ipstr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip = !empty($ipstr) ? ip2long($ipstr) : 0;		
		$public_key = C('PUBLIC_KEY');
		$key = md5($public_key);
		
		$destr = decrypt($str, $key);
		if(!empty($destr)) {
			$arr = explode("-", $destr);
			if(count($arr) < 3) {
				return $arr;
			} else {
				return array('uid'=>$arr[0],'time'=>$arr[1],'ip'=>$arr[2]);
			}
		} else {
			return $arr;
		}
	}
	
	public function useraction() {
		$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		isset($_COOKIE['useraction'])&&!empty($_COOKIE['useraction']) && $cookiestr = $_COOKIE['useraction'];							
		$ipstr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$ip = !empty($ipstr) ? ip2long($ipstr) : 0;		
		$cookiearr = !empty($cookiestr) ? $this->decrypt_cookie($cookiestr) : array();
		if(!empty($cookiearr)) {
			$tmp = $time - intval($cookiearr['time']);
			if($tmp > 3600) {
				misc::set_cookie('useraction', '',  $time+ 3600, '/', TRUE);
			} else {
				$cookiearr['clicknum'] = $cookiearr['clicknum']+1;
				return $this->encrypt_cookie($cookiearr);
			}
		} else {
			$cookiearrb = array('cookiestr'=>'kaoder', 'clicknum'=>1, 'time'=>$time, 'ip'=>$ip);
			return $this->encrypt_cookie($cookiearrb);
		}							
	}
	
	public function bannedip() {
		$ip = $_SERVER['REMOTE_ADDR'];
		$iparr = explode('.', $ip);
		$cookiepre = C('COOKIE_PREFIX');
		if(!empty($iparr)) {
			$banned = $this->banned->get_banned_by_ip($iparr[0], $iparr[1], $iparr[2], $iparr[3]);
			if(!empty($banned)) {
				$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
				if(empty($banned['expiration'])) {
					setcookie($cookiepre.'auth', '', 0, '/',false);
					$this->message('IP被禁，请稍候再试', 0);
					exit;
				} elseif ($time < $banned['expiration']){
					setcookie($cookiepre.'auth', '', 0, '/',false);
					$this->message('IP被禁，请稍候再试', 0);
					exit;
				} else {
					return false;
				}
			}else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public function addforumauto($fid, $type, $user = array()) {
		//自动加入园子
		$forum_user = $this->forumuser->get_forum_user($fid, $user['uid']);
		if(!empty($forum_user)) {
			if($type == 'message') {
				$forum_user['messages'] = $forum_user['messages'] + 1;
				$this->forumuser->update($forum_user['fuid'], $forum_user);
			}
			
			if($type == 'thread') {
				$forum_user['threads'] = $forum_user['threads'] + 1;
				$forum_user['contribations'] = $forum_user['contribations'] + 2;
				$this->forumuser->update($forum_user['fuid'], $forum_user);
			}
			
			if($type == 'post') {
				$forum_user['posts'] = $forum_user['posts'] + 1;
				$this->forumuser->update($forum_user['fuid'], $forum_user);
			}
			
		} else {
			$forumuserarr = array();
			$forumuserarr['fid'] = $fid;
			$forumuserarr['uid'] = $user['uid'];
			$forumuserarr['groupid'] = 11;
			$forumuserarr['username'] = $user['username'];
			$forumuserarr['ustars'] = 0;
			$forumuserarr['threads'] = ($type == 'thread') ? 1:0 ;
			$forumuserarr['invests'] = 0;
			$forumuserarr['posts'] = ($type == 'post') ? 1:0 ;
			$forumuserarr['messages'] = ($type == 'message') ? 1:0 ;
			$forumuserarr['astar5'] = 0;
			$forumuserarr['contribations'] = ($type == 'thread') ? 2:0 ;
			$forumuserarr['comment'] = '';
			$forumuserarr['status'] = 0;
			$forumuserarr['isvisible'] = 0;
			$forumuserarr['dateline'] = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
			$forumuserarr['uptime'] = 0;
			$forumuserarr['issubscribe'] = 0;					
			$this->forumuser->_create($forumuserarr);
			
			// 精选社用户加1
			$forumarr = $this->forum->read($fid);
			$forumarr['users'] += 1; 
			$this->forum->update($fid, $forumarr);
		}
	}
	
	public function checkIP() {
		//$ip = $_SERVER['REMOTE_ADDR'];
		$ip = get_client_ip();
		$res = true;
	
		$c_ip = '';
		$ip_arr = explode('.', $ip);
		if(!empty($ip_arr)) {
			foreach ($ip_arr as $key => $val) {
				if($key < 3) {
					$c_ip .= $val.'.';
				}
			}
		}
	
		if(!isset($_SERVER['badip'])) {
			$_SERVER['badip'] = @include WWW_PATH.'Conf/badip.php';
		}
		if(!empty($_SERVER['badip'])) {
			foreach ($_SERVER['badip'] as $key => $val) {
				$val_arr = explode('.', $val);
				if(!empty($val_arr)) {
					foreach ($val_arr as $k => $v) {
						if($v == '*') {
							$c_ip_full = $c_ip."*";
							if($c_ip_full == $val) {
								$res = false; break;
							}
								
						} else {
							if($val == $ip) {
								$res = false; break;
							}
						}
					}
				}
				if($res == false) break;
			}
		}
	
		$res_arr = array(
				'res' => $res,
				'ip_arr' => $ip_arr,
		);
		return $res_arr;
	}
	
	/**
	 * # 区别于 sendnotice(旧版)
	 *
	 * # 用途：取代 sendnotice, 发送通知
	 * # 参数：arr('touid', 'message', 'pmtype', 'fid', 'tid', 'surl', 'fromuid', 'fromuser') // 后2个参数目前为常量，可不传
	 */
	public function sendnotices($arr) {
		$uid = $arr['touid'];
		$email = 'admin@kaoder.com';
		$admin = $this->user->get_user_by_email($email);
		$time = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
		
		if(empty($arr['fromuid'])) {
			$arr['fromuid'] = $admin['uid'];
		}
		$arr['dateline'] = $time;
		$arr['fromuser'] = '系统管理员';
		
		if(!empty($admin)) {
			$this->notices->_create($arr);
			//更新用户事件
			$event = $this->userevent->get_new_event($uid);
			if(!empty($event)) {
				$event['newnotice'] = $event['newnotice']+1;
				$this->userevent->update($event['evenid'], $event);
			} else {
				$arr = array('uid'=>$uid, 'newpm'=>0, 'newnotice'=>1, 'newfans'=>0, 'newevaluate'=>0, 'newreply'=>0, 'newmessage'=>0);
				$this->userevent->_create($arr);
			}
		}
		return true;
	}
	
}