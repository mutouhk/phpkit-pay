<?php

/**
 * 通用支付接口类
 * @author yunwuxin<448901948@qq.com>
 */

namespace phpkit\pay;

/**
 * 数据库
 * CREATE TABLE `think_pay` (
 * `out_trade_no` varchar(100) NOT NULL,
 * `money` decimal(10,2) NOT NULL,
 * `status` tinyint(1) NOT NULL DEFAULT '0',
 * `callback` varchar(255) NOT NULL,
 * `url` varchar(255) NOT NULL,
 * `param` text NOT NULL,
 * `create_time` int(11) NOT NULL,
 * `update_time` int(11) NOT NULL,
 * PRIMARY KEY (`out_trade_no`)
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 */
class Pay {

	/**
	 * 支付驱动实例
	 * @var Object
	 */
	private $payer;

	/**
	 * 配置参数
	 * @var type
	 */
	private $config;

	/**
	 * 构造方法，用于构造上传实例
	 * @param string $driver 要使用的支付驱动
	 * @param array $config 配置
	 */
	public function __construct() {

	}

	public function config($driver, $config = array()) {
		/* 配置 */
		$pos = strrpos($driver, '\\');
		$pos = $pos === false ? 0 : $pos + 1;
		$apitype = strtolower(substr($driver, $pos));
		// if ($_GET['storeDomain']) {
		// 	$subDomain = "@" . $_GET['storeDomain'];
		// }

		// $url = HomeUrl . "/BoxPay/Index/notify";
		// $this->config['notify_url'] = $url . "/method/notify/";
		// $this->config['return_url'] = $url . "/method/return/";
		//$config = array_merge($this->config, $config);
		$this->payDone = false;
		/* 设置支付驱动 */
		if ($driver) {
			$class = strpos($driver, '\\') ? $driver : 'phpkit\pay\\Pay\\Driver\\' . ucfirst(strtolower($driver));
			$this->setDriver($class, $config);
		}
		return $this;
	}

	//支付成功之后， 可以获得 ，订单号
	public function getOrderNo() {
		$out_trade_no = $_REQUEST["out_trade_no"]; //pc端支付宝回调

		$notify_data = stripslashes(htmlspecialchars_decode($_REQUEST['notify_data'])); //支付宝手机网页支付异步回调
		if ($notify_data) {
			$doc = new \DOMDocument();
			$doc->loadXML($notify_data);
			if (!empty($doc->getElementsByTagName("notify")->item(0)->nodeValue)) {
				$out_trade_no = $doc->getElementsByTagName("out_trade_no")->item(0)->nodeValue;
			}
			// BoxModel("test")->add(array('test'=>$out_trade_no));//测试可以删除
		}
		$xml = $GLOBALS['HTTP_RAW_POST_DATA']; //微信JS支付
		if ($xml) {
			// BoxModel("test")->add(array('test'=>$xml));
			$this->weixinNotifyData = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
			// BoxModel("test")->add(array('test'=>json_encode($r)));
			$out_trade_no = $this->weixinNotifyData['out_trade_no'];
		}
		if ($_GET['tradeNum']) {
			$out_trade_no = $_REQUEST['tradeNum']; //京东支付回调的订单号
		}
		$this->OrderNo = $out_trade_no;
		return $out_trade_no;
	}
	//支付成功
	public function success($successFuc = "") {
		$isPost = false;
		if (!empty($_POST)) {
			$isPost = true;
			$notify = $_POST;
		} elseif (!empty($_GET)) {
			$notify = $_GET;
		} elseif ($this->weixinNotifyData) {
			$isPost = true;
			$notify = $this->weixinNotifyData;
		} else {
			throw new \Exception("Access Denied", 1);
		}
		unset($notify['_url']);
		unset($notify['method']);
		unset($notify['storeDomain']);
		//验证
		if ($this->verifyNotify($notify)) {
			//获取订单信息
			$info = $this->getInfo();
			if ($info['status'] == 1) {
				if (!empty($successFuc)) {
					$successFuc($info);
				}
				if ($isPost === true) {
					$pay->notifySuccess();
				}

			} else {
				$this->payFail = true;
				$this->errorInfo = $info;
			}
		} else {
			$notify['msg'] = 'verifyNotify 没通过';
			$this->errorInfo = $notify;
			$this->payFail = true;
			//$this->myError("支付失败，请稍后再试，或联系管理员", $url);
		}
		return $this;
	}
	//支付失败
	public function fail($failFuc = '') {
		if ($this->payFail === true) {
			if (!empty($failFuc)) {
				$failFuc($this->errorInfo);
			}
		}
		return $this;
	}
	//参数设置
	public function setParams($config = array()) {
		$this->vo = new Pay\PayVo();
		$params = array();
		foreach ($config as $key => $value) {
			$method = "set" . ucfirst($key);
			if (method_exists($this->vo, $method)) {
				$this->vo->$method($value);
			} else {
				$params[$key] = $value;
			}
		}
		$this->vo->setParam($params);
		return $this;
	}
	//去支付
	public function run() {
		$FormContent = $this->buildRequestForm($this->vo);
		echo $FormContent;
		return $this;
	}

	public function buildRequestForm(Pay\PayVo $vo) {
		//生成本地记录数据
		return $this->payer->buildRequestForm($vo);
	}

	/**
	 * 设置支付驱动
	 * @param string $class 驱动类名称
	 */
	private function setDriver($class, $config) {
		$this->payer = new $class($config);
		if (!$this->payer) {
			throw new \Exception("不存在支付驱动：{$class}");
		}
	}

	public function __call($method, $arguments) {
		if (method_exists($this, $method)) {
			return call_user_func_array(array(&$this, $method), $arguments);
		} elseif (!empty($this->payer) && $this->payer instanceof Pay\Pay && method_exists($this->payer, $method)) {
			return call_user_func_array(array(&$this->payer, $method), $arguments);
		}
	}

}
