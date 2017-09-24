<?php
/**
 * config.php
 * 百度贴吧抢二楼配置文件
 * @var string
 */

return array(
	// 帐号cookie，不要带上“BDUSS=”等字段
	'bduss' => '',
	// 需要抢二楼的贴吧数组
	'forum' => ['昌维', '昌维吧', '渗透'],
	// 可抢二楼检测起始页
	'page_start' => 1,
	// 可抢二楼检测结束页
	'page_end' => 2,
	// 两次发帖间隔时间
	'sleep' => 5,
	// 发帖内容文本文件路径，一行一个
	'content' => 'content.txt',
	// 日志输出文本文件路径
	'log' => 'log.txt',
);