<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); ini_set('zend.exception_ignore_args','1');
require_once __DIR__ . '/support/password_recovery_fixture.php';
$tests=new RecoveryTests();
$old=[];
foreach(['FOURSMART_SMTP_PORT','FOURSMART_SMTP_TIMEOUT','FOURSMART_RECOVERY_HMAC_KEY_FILE'] as $name) {
    $old[$name]=getenv($name);putenv($name);
}
try {
    $tests->test('real CLI worker refuses disabled recovery before database access and emits a safe error',function():void {
        $env=getenv();$env['FOURSMART_PASSWORD_RECOVERY_ENABLED']='0';unset($env['FOURSMART_DATABASE_CONFIG']);
        $p=proc_open([PHP_BINARY,__DIR__.'/../cli/password_recovery_worker.php','--once'],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env,['bypass_shell'=>true]);
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
        recoveryAssert($code===1 && $out==='' && $err==="Worker unavailable: check private configuration and approved schema.\n",'Unsafe worker refusal');
    });
    $tests->test('real PHPMailer connection failure retries a SQLite fixture three times with bounded delays and safe codes',function():void {
        $db=new PDO('sqlite::memory:');recoveryFixture($db);$model=new PasswordRecoveryModel($db);
        $socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);$address=stream_socket_get_name($socket,false);fclose($socket);
        putenv('FOURSMART_SMTP_PORT='.parse_url('tcp://'.$address,PHP_URL_PORT));putenv('FOURSMART_SMTP_TIMEOUT=1');
        $clock=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $service=new PasswordRecoveryService($model,['key'=>random_bytes(32),'url'=>'http://localhost/FourSmart/mvc/index.php'],null,function()use(&$clock){return $clock;});
        $service->request('customer@example.test','127.0.0.1');
        foreach([1,2,3] as $attempt) {
            recoveryAssert($service->processOne()==='retry_or_failed','SMTP failure result changed');
            $job=$db->query('SELECT * FROM password_reset_jobs')->fetch();
            recoveryAssert((int)$job['attempts']===$attempt && $job['last_error_code']==='SMTP_FAILED','Unsafe retry state');
            if($attempt<3) {
                recoveryAssert($job['status']==='pending' && $service->processOne()==='idle','Backoff not enforced');
                recoveryAssert($job['available_at']===PasswordRecoveryModel::stamp($clock->modify('+'.($attempt*10).' seconds')),'Incorrect retry delay');
                $clock=$clock->modify('+'.($attempt*10).' seconds');
            } else recoveryAssert($job['status']==='failed' && $service->processOne()==='idle','Retry limit exceeded');
        }
    });
    $tests->test('real SMTP timeout returns a safe retry and releases the SQLite fixture lease',function():void {
        $peer=proc_open([PHP_BINARY,__DIR__.'/support/local_smtp_failure.php','timeout'],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        recoveryAssert(is_resource($peer),'Peer startup failed');
        try {
            fclose($pipes[0]);stream_set_timeout($pipes[1],5);$address=trim((string)fgets($pipes[1]));
            recoveryAssert(preg_match('/\A127\.0\.0\.1:[0-9]+\z/',$address)===1,'Peer endpoint invalid');
            putenv('FOURSMART_SMTP_PORT='.parse_url('tcp://'.$address,PHP_URL_PORT));putenv('FOURSMART_SMTP_TIMEOUT=1');
            $db=new PDO('sqlite::memory:');recoveryFixture($db);$model=new PasswordRecoveryModel($db);
            $service=new PasswordRecoveryService($model,['key'=>random_bytes(32),'url'=>'http://localhost/FourSmart/mvc/index.php']);
            $service->request('customer@example.test','127.0.0.1');$start=microtime(true);
            recoveryAssert($service->processOne()==='retry_or_failed','Timeout did not retry');
            recoveryAssert(microtime(true)-$start<5,'Timeout was unbounded');
            $job=$db->query('SELECT * FROM password_reset_jobs')->fetch();
            recoveryAssert($job['status']==='pending' && $job['lock_token']===null && $job['locked_until']===null
                && $job['last_error_code']==='SMTP_FAILED','Timeout did not release lease safely');
        } finally {proc_terminate($peer);fclose($pipes[1]);fclose($pipes[2]);proc_close($peer);}
    });
} finally {foreach($old as $name=>$value)putenv($value===false?$name:$name.'='.$value);}
$tests->finish();
