<?php
/**
* TiebaPostMan 百度贴吧抢二楼
* @author:昌维[867597730@qq.com]
* @repo:https://github.com/cw1997/tieba-postman
* @date:2017-09-24 16:30:39
*/
class TiebaPostMan
{
	private $config;
	private $content;
	private $content_num;
	function __construct($config)
	{
		$this->config = $config;
		// 验证掉线马甲并且缓存tbs
		$this->_checkBDUSS();
		// 缓存水贴内容
		$this->_cacheContent();
	}
	public function cron()
	{
		$this->run();
	}
	public function demaon()
	{
		while (1) {
			$this->run();
			sleep(1);
		}
	}
	public function run()
	{
		foreach ($this->config['forum'] as $forum_index => $forum_name) {
			$forum = $this->_getForum($forum_name, $this->config['bduss'], $this->config['page_start'], $this->config['page_end']);
			// print_r($forum);
			// 无帖子的情况下跳过循环
			if (is_null($forum['thread_list'])) {
				continue;
			}
			foreach ($forum['thread_list'] as $thread_index => $thread) {
				$thread_id = $thread['tid'];
				$reply_num = $thread['reply_num'];
				// 是否新帖判断
				if ($reply_num != 0) {
					continue;
				}
				$tbs = $this->config['tbs'];
				$content = $this->_getContent();
				$post_json = $this->_postThread($thread_id, $forum['forum_id'], $forum['forum_name'], $this->config['bduss'], $tbs, $content);
				// 打日志
				$log = array();
				$log['post_json'] = $post_json;
				$log['thread_id'] = $thread_id;
				$log['forum_id'] = $forum['forum_id'];
				$log['forum_name'] = $forum['forum_name'];
				$log['bduss'] = $this->config['bduss'];
				$log['tbs'] = $tbs;
				$log['content'] = $content;
				$log['title'] = $thread['title'];
				$this->_log($log);
				// 反SPAM休眠策略
				sleep($this->config['sleep']);
			}
		}
	}
	private function _checkBDUSS()
	{
		$bduss = $this->config['bduss'];
		$ret_json = $this->_curl('http://tieba.baidu.com/dc/common/tbs', '', "BDUSS={$bduss}");
		$ret = json_decode($ret_json, 1);
		if ($ret['is_login'] == 0) {
			echo date('y-m-d H:i:s',time()) . "BDUSS已失效，请重新获取并在配置文件中更换。\n";
			return false;
		}
		$this->config['tbs'] = $ret['tbs'];
		return true;
	}
	private function _cacheContent()
	{
		$content = file_get_contents('./' . $this->config['content']);
		$this->content = explode("\n", $content);
		$this->content_num = count($this->content);
	}
	private function _getContent()
	{
		$content = $this->content[rand(0, $this->content_num - 1)];
		return $content;
	}
	/**
	 * 打日志
	 * 日志格式 => "时间 贴吧id 帖子id 贴吧名字 帖子标题 回帖内容 返回消息"
	 * TODO：该函数出现错误码为0时只打post_json的情况
	 * @param  array $log 日志数组
	 * @return void
	 */
	private function _log($log)
	{
		$log_content = date('y-m-d H:i:s ',time()) . $log['forum_id'] . ' ' . $log['thread_id'] . ' ' . $log['forum_name'] . ' ' . $log['title'] . ' ' . $log['content'] . ' ' . $log['post_json'] . "\n";
		echo $log_content;
		file_put_contents('./' . $this->config['log'], $log_content, FILE_APPEND);
	}
	private function _getForum($forum_name, $bduss, $page_start, $page_end)
	{
	    $ret = array();
	    $forum_json = array();
	    for ($i=$page_start; $i < $page_end; $i++) {
			$data = array(
				'BDUSS='.$bduss,
		        '_client_id=wappc_1396611108603_817',
		        '_client_type=2',
		        '_client_version=5.7.0',
		        'from=tieba',
		        // 'ie=utf8',
		        'kw='.$forum_name,
		        'pn='.$i,
		        'q_type=2',
		        'rn=50',
		        'with_group=1'
		    );
	    	$forum_json = $this->_tiebaPostByAndroidClient('http://c.tieba.baidu.com/c/f/frs/page', $data);
	    	$forum_json = json_decode($forum_json, 1);
	    	// print_r($forum_json);
	    }
	    $ret['forum_id'] = $forum_json['forum']['id'];
	    $ret['forum_name'] = $forum_json['forum']['name'];
	    $ret['thread_list'] = array();
	    if (!is_null($forum_json['thread_list'])) {
	    	$ret['thread_list'] = array_merge($forum_json['thread_list'], $ret['thread_list']);
	    }
	    return $ret;
	}
	/**
	 * 电脑网页版发帖接口
	 * TODO：发帖成功，但是会被判断为SPAM删帖，删帖原因为违规内容，初步判断为_BSK参数问题
	 * @param  [type] $thread_id  [description]
	 * @param  [type] $forum_id   [description]
	 * @param  [type] $forum_name [description]
	 * @param  [type] $bduss      [description]
	 * @param  [type] $tbs        [description]
	 * @param  [type] $content    [description]
	 * @return [type]             [description]
	 */
	private function _oldPostThread($thread_id, $forum_id, $forum_name, $bduss, $tbs, $content)
	{
		$content = urlencode($content);
		$post_json = $this->_curl(
			'http://tieba.baidu.com/f/commit/post/add',
			"ie=utf-8&kw={$forum_name}&fid={$forum_id}&tid={$thread_id}&tbs={$tbs}&content={$content}&rich_text=1&floor_num=1&basilisk=1&__type__=reply",
			'BDUSS='.$bduss
		);
		return $post_json;
	}
	/**
	 * 安卓客户端发帖接口
	 * TODO：发不出贴，报未知错误
	 * @param  [type] $thread_id  [description]
	 * @param  [type] $forum_id   [description]
	 * @param  [type] $forum_name [description]
	 * @param  [type] $bduss      [description]
	 * @param  [type] $tbs        [description]
	 * @param  [type] $content    [description]
	 * @return [type]             [description]
	 */
	private function _postThread($thread_id, $forum_id, $forum_name, $bduss, $tbs, $content)
	{
		$data = array(
			'BDUSS='.$bduss,
	        '_client_type=102',
	        '_client_version=1.3.1',
	        'anonymous=1',
	        'appid=bazhu',
	        'content='.urlencode($content),
	        'fid='.$forum_id,
	        'from=1006294p',
	        'is_ad=0',
	        'is_location=2',
	        'kw='.urlencode($forum_name),
	        'model=Mi-4c',
	        'new_vcode=1',
	        'stErrorNums=1',
	        'stMethod=1',
	        'stMode=1',
	        'stSize=1021',
	        'stTime=1352',
	        'stTimesNum=1',
	        'subapp_type=admin',
	        'tbs='.$tbs,
	        'tid='.$thread_id,
	        'timestamp='.time(),
	        'vcode_tag=11'
	    );
		$post_json = $this->_tiebaPostByAndroidClient('http://c.tieba.baidu.com/c/c/bawu/delthread', $data);
		return $post_json;
	}
	/**
	 * 普通curl函数
	 * @param  [type] $url    [description]
	 * @param  string $post   [description]
	 * @param  string $cookie [description]
	 * @param  array  $header [description]
	 * @return [type]         [description]
	 */
	private function _curl($url, $post = '', $cookie = '', $header = array())
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		if ($post != '') {
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
		}
		if ($cookie != '') {
			curl_setopt($ch, CURLOPT_COOKIE,$cookie);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	/**
	 * 安卓客户端通用发帖接口，自动处理sign签名
	 * @param  [type] $url  [description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	private function _tiebaPostByAndroidClient($url, $data = null)
	{
		$data_with_sign = implode('&', $data) . '&sign=' . md5(implode('', $data) . 'tiebaclient!!!');
		$header = array("Content-Type: application/x-www-form-urlencoded");
		return $this->_curl($url, $data_with_sign, '', $header);
	}
}