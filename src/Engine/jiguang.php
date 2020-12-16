<?php

namespace PhalApi\Xpush\Engine;

class Jiguang
{
    protected $config;
    protected $debug;
    protected $client;

    public function __construct($config = null)
    {
        $di = \PhalApi\DI();
        $this->debug = $di->debug;
        $this->config = $config;
        if (null == $this->config) {
            $this->config = $di->config->get('app.Xpush.jiguang');
        }
        $log_path = API_ROOT.'/runtime/jpush/'.date('Ymd', time()).'.log';
        $this->client = new \JPush\Client($this->config['app_key'], $this->config['master_secret'], $log_path);
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 推送接口.
     */
    public function push($device_id, $device_type, $content, $title, $extras, $sendno = null)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        if (!empty($device_id)) {
            $pusher = $this->client->push();
            //$cid = md5(implode(',', $extras));
            //$pusher->setCid($cid);
            $pusher->addRegistrationId([$device_id]);
            if (false !== strpos('android', strtolower($device_type))) {
                $pusher->setPlatform('android');
                $android_notification = [
                    'title' => $title,
                    //'category' => $cid,
                    'extras' => $extras,
                ];
                $pusher->androidNotification($content, $android_notification);
            } elseif (false !== strpos('ios', strtolower($device_type))) {
                $pusher->setPlatform('ios');
                $ios_notification = [
                    //'alert' => $content,
                    'badge' => '+1',
                    //'thread-id' => $cid,
                    'extras' => $extras,
                ];
                $pusher->iosNotification($content, $ios_notification);
            } else {
                $pusher->setPlatform('all');
                $android_notification = [
                    'title' => $title,
                    //'category' => $cid,
                    'extras' => $extras,
                ];
                $pusher->androidNotification($content, $android_notification);
                $ios_notification = [
                    //'alert' => $content,
                    'badge' => '+1',
                    //'thread-id' => $cid,
                    'extras' => $extras,
                ];
                $pusher->iosNotification($content, $ios_notification);
            }
            $options = [
                'time_to_live' => 0,
            ];
            if (!empty($sendno)) {
                $options['sendno'] = $sendno;
            }
            try {
                $response = $pusher->options($options)->send();
                if (200 === $response['http_code']) {
                    $rs['code'] = 1;
                    $rs['msg'] = 'success';
                    $rs['data'] = $response['body'];
                    if ($this->debug) {
                        $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['rs' => $response['body']]);
                    }
                }
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $rs['code'] = -1;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $rs['code'] = -2;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        } else {
            $rs['code'] = -3;
            $rs['msg'] = 'Empty Params';
        }

        return $rs;
    }

    /**
     * 获取送达统计
     */
    public function getReceived($msg_id)
    {
        $di = \PhalApi\DI();
        if (!empty($device_id)) {
            $report = $this->client->report();
            try {
                return $report->getReceived($msg_id);
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        }

        return -1;
    }

    /**
     * 获取消息统计
     */
    public function getMessages($msg_id)
    {
        $di = \PhalApi\DI();
        if (!empty($device_id)) {
            $report = $this->client->report();
            try {
                return $report->getMessages($msg_id);
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        }

        return -1;
    }

    /**
     * 送达状态查询.
     */
    public function getMessageStatus($msgId, $deviceId)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        if (!empty($msgId) && !empty($deviceId)) {
            try {
                $report = $this->client->report();
                $response = $report->getMessageStatus(intval($msgId), $deviceId);
                $rs['code'] = 1;
                $rs['msg'] = 'success';
                $rs['data'] = $response;
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $rs['code'] = -1;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $rs['code'] = -2;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        } else {
            $rs['code'] = -3;
            $rs['msg'] = 'Empty Params';
        }

        return $rs;
    }

    /**
     * 获取用户在线状态
     */
    public function getDevicesStatus($deviceIds)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        if (!empty($deviceIds)) {
            try {
                $device = $this->client->device();
                $response = $device->getDevicesStatus($deviceIds);
                $rs['code'] = 1;
                $rs['msg'] = 'success';
                $rs['data'] = $response;
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $rs['code'] = -1;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $rs['code'] = -2;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        } else {
            $rs['code'] = -3;
            $rs['msg'] = 'Empty Params';
        }

        return $rs;
    }

    /**
     * 更新 mobile.
     */
    public function updateMoblie($deviceId, $phoneno)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        if (!empty($deviceId)) {
            try {
                $device = $this->client->device();
                $response = $device->updateMoblie($deviceId, $phoneno);
                $rs['code'] = 1;
                $rs['msg'] = 'success';
                $rs['data'] = $response;
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $rs['code'] = -1;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $rs['code'] = -2;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        } else {
            $rs['code'] = -3;
            $rs['msg'] = 'Empty Params';
        }

        return $rs;
    }

    /**
     * 取消手机绑定.
     */
    public function clearMobile($deviceId)
    {
        $di = \PhalApi\DI();
        $rs = [
            'code' => 0,
            'msg' => '',
            'data' => null,
        ];
        if (!empty($deviceId)) {
            try {
                $device = $this->client->device();
                $response = $device->clearMobile($deviceId);
                $rs['code'] = 1;
                $rs['msg'] = 'success';
                $rs['data'] = $response;
            } catch (\JPush\Exceptions\APIConnectionException $e) {
                $rs['code'] = -1;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIConnectionException' => $e->__toString()]);
            } catch (\JPush\Exceptions\APIRequestException $e) {
                $rs['code'] = -2;
                $rs['msg'] = $e->__toString();
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['APIRequestException' => $e->__toString()]);
            }
        } else {
            $rs['code'] = -3;
            $rs['msg'] = 'Empty Params';
        }

        return $rs;
    }
}
