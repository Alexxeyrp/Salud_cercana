<?php
if (PHP_SAPI !== 'cli' || getenv('SALUD_TEST_DATABASE') !== 'salud_demo_test') exit('Use only the isolated salud_demo_test database via CLI.');
$base=getenv('SALUD_TEST_URL') ?: 'http://127.0.0.1:8766/';
if (!preg_match('~^http://127[.]0[.]0[.]1:[0-9]+/$~', $base)) exit('Local test URL required.');
function req($path,$data=null,$jar='a') {global $base; $h=curl_init($base.$path);curl_setopt_array($h,[CURLOPT_TIMEOUT=>15,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>sys_get_temp_dir().'/salud-'.$jar.'.cookie',CURLOPT_COOKIEJAR=>sys_get_temp_dir().'/salud-'.$jar.'.cookie']);if($data!==null){curl_setopt($h,CURLOPT_POST,true);curl_setopt($h,CURLOPT_POSTFIELDS,is_array($data)&&array_filter($data,fn($v)=>$v instanceof CURLFile)?$data:http_build_query($data));}$body=curl_exec($h);$code=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);return [$code,$body];}
function token($path,$jar='a'){[, $b]=req($path,null,$jar);preg_match_all('/name="csrf_token" value="([a-f0-9]+)"/',$b,$m);return !empty($m[1])?end($m[1]):'';}
function check($ok,$name){echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)$GLOBALS['failed']=true;}
$pdo=new PDO('mysql:host=127.0.0.1;port=13317;dbname=salud_demo_test','root','');
if ($pdo->query('SELECT COUNT(*) FROM users_data')->fetchColumn() != 0) exit('Test database must be empty.');
check(req('index.php')[0]===200,'home');check(req('perfil.php')[0]===302,'anonymous profile denied');
foreach(['a','b','admin'] as $j){$d=['csrf_token'=>token('registro.php',$j),'nombre'=>'Persona','apellidos'=>'Ficticia','email'=>$j.'@example.invalid','telefono'=>'600000000','fecha_nacimiento'=>'1990-01-01','direccion'=>'Calle ficticia','sexo'=>'otro','usuario'=>'demo_'.$j,'password'=>bin2hex(random_bytes(16))];$passwords[$j]=$d['password'];check(req('registro.php',$d,$j)[0]===302,'register '.$j);}
$pdo->exec("UPDATE users_login SET rol='admin' WHERE usuario='demo_admin'");
check(req('login.php',['usuario'=>'demo_a','password'=>$passwords['a']])[0]===200,'login requires CSRF');
foreach(['a','b','admin'] as $j)check(req('login.php',['csrf_token'=>token('login.php',$j),'usuario'=>'demo_'.$j,'password'=>$passwords[$j]],$j)[0]===302,'login '.$j);
check(req('usuarios-administracion.php')[0]===302,'user cannot administer');
check(req('citaciones.php',['csrf_token'=>token('citaciones.php'),'action'=>'create','fecha_cita'=>date('Y-m-d',strtotime('+2 days')),'motivo_cita'=>'Consulta ficticia'])[0]===302,'create appointment');
$id=$pdo->query('SELECT MAX(idCita) FROM citas')->fetchColumn();
[, $body]=req('citaciones.php',['csrf_token'=>token('citaciones.php','b'),'action'=>'update','idCita'=>$id,'fecha_cita'=>date('Y-m-d',strtotime('+3 days')),'motivo_cita'=>'Ajena'],'b');check(str_contains($body,'no te pertenece'),'foreign appointment rejected');
check(req('citaciones.php',['csrf_token'=>token('citaciones.php'),'action'=>'update','idCita'=>$id,'fecha_cita'=>date('Y-m-d',strtotime('+3 days')),'motivo_cita'=>'Editada'])[0]===302,'update own appointment');
check(req('citaciones.php',['csrf_token'=>'bad','action'=>'delete','idCita'=>$id])[0]===200&&$pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn()==1,'CSRF prevents deletion');
$file=tempnam(sys_get_temp_dir(),'salud-image');file_put_contents($file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
check(req('crear_noticia.php',['csrf_token'=>token('crear_noticia.php','admin'),'titulo'=>'Noticia ficticia prueba','texto'=>'Contenido ficticio','fecha'=>date('Y-m-d'),'imagen'=>new CURLFile($file,'image/png','demo.png')],'admin')[0]===302,'admin uploads image');
$image=$pdo->query('SELECT imagen FROM noticias LIMIT 1')->fetchColumn();check($image&&req($image)[0]===200,'uploaded image survives next request');
$newsId=$pdo->query('SELECT idNoticia FROM noticias LIMIT 1')->fetchColumn();
check(req('noticias-administracion.php',['csrf_token'=>token('noticias-administracion.php','admin'),'action'=>'update','idNoticia'=>$newsId,'titulo'=>'Noticia editada ficticia','texto'=>'Texto editado','fecha'=>date('Y-m-d')],'admin')[0]===302,'admin edits news');
check(req('noticias-administracion.php',['csrf_token'=>token('noticias-administracion.php','admin'),'action'=>'delete','idNoticia'=>$newsId],'admin')[0]===302 && !is_file(getenv('SALUD_ROOT').'/'.$image),'admin deletes news and image');
$image=null;
check(req('perfil.php',['csrf_token'=>token('perfil.php'),'action'=>'data','nombre'=>'Persona','apellidos'=>'Ficticia','email'=>'a@example.invalid','telefono'=>'600000001','fecha_nacimiento'=>'1990-01-01','direccion'=>'Otra calle ficticia','sexo'=>'otro'])[0]===302,'update profile');
$newPassword=bin2hex(random_bytes(16));
check(req('perfil.php',['csrf_token'=>token('perfil.php'),'action'=>'password','password_actual'=>$passwords['a'],'password_nueva'=>$newPassword,'password_confirmacion'=>$newPassword])[0]===302,'change password');
$hash=$pdo->query("SELECT password FROM users_login WHERE usuario='demo_a'")->fetchColumn();check(password_verify($newPassword,$hash)&&!password_verify($passwords['a'],$hash),'new password persisted old password rejected');
$pdo->exec("UPDATE users_login SET rol='user' WHERE usuario='demo_admin'");check(req('crear_noticia.php',null,'admin')[0]===302,'role revocation immediate');
check(req('citaciones.php',['csrf_token'=>token('citaciones.php'),'action'=>'delete','idCita'=>$id])[0]===302 && $pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn()==0,'delete own appointment');
check(req('logout.php')[0]===405,'logout rejects GET');check(req('logout.php',['csrf_token'=>token('index.php')])[0]===302,'logout');check(req('citaciones.php')[0]===302,'logout removes access');
// Only remove files created by this isolated test.
if($image)unlink(getenv('SALUD_ROOT').'/'.$image);unlink($file);
exit(empty($GLOBALS['failed'])?0:1);
