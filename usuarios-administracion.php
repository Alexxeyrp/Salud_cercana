<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php'; requireAdmin(); require_once __DIR__.'/config/conexion.php';
$errors=[];$message=$_SESSION['flash']??'';unset($_SESSION['flash']);$adminId=(int)$_SESSION['idUser'];
if($_SERVER['REQUEST_METHOD']==='POST') {
 if(!csrfValid('admin_users',$_POST['csrf_token']??null)) $errors[]='La solicitud no es válida.'; else {
  $action=$_POST['action']??'';$id=(int)($_POST['idUser']??0);
  if($action==='delete') {
   if($id <= 0) {
    $errors[]='El usuario indicado no es válido.';
   } elseif($id===$adminId) {
    $errors[]='No puedes eliminar tu propia cuenta.';
   } else {
    $exists=$pdo->prepare('SELECT 1 FROM users_login WHERE idUser=?');
    $exists->execute([$id]);
    if(!$exists->fetchColumn()) {
     $errors[]='El usuario indicado no existe.';
    } else {
     $rel=$pdo->prepare('SELECT (SELECT COUNT(*) FROM citas WHERE idUser=?) + (SELECT COUNT(*) FROM noticias WHERE idUser=?)');
     $rel->execute([$id,$id]);
     if((int)$rel->fetchColumn()>0) {
      $errors[]='No se puede eliminar este usuario porque tiene citas o noticias asociadas.';
     } else {
      try {
       $pdo->beginTransaction();
       $eliminarLogin=$pdo->prepare('DELETE FROM users_login WHERE idUser=?');
       $eliminarLogin->execute([$id]);
       $eliminarDatos=$pdo->prepare('DELETE FROM users_data WHERE idUser=?');
       $eliminarDatos->execute([$id]);
       $pdo->commit();
       flash('Usuario eliminado.');
       header('Location: usuarios-administracion.php');
       exit;
      } catch (Throwable $exception) {
       if($pdo->inTransaction()) $pdo->rollBack();
       $errors[]='No se ha podido eliminar el usuario.';
      }
     }
    }
   }
  } else {
   $fields=['nombre','apellidos','email','telefono','fecha_nacimiento','direccion','sexo'];$data=[];foreach($fields as $f)$data[$f]=trim((string)($_POST[$f]??''));$rol=$_POST['rol']??'';$usuario=trim((string)($_POST['usuario']??''));$password=(string)($_POST['password']??'');$errors=validatePersonalData($data);
   if(!in_array($rol,['user','admin'],true))$errors[]='El rol no es válido.'; if($action==='create' && !preg_match('/^[A-Za-z0-9_]{3,30}$/',$usuario))$errors[]='El usuario debe tener entre 3 y 30 caracteres alfanuméricos o guion bajo.'; if($action==='create'&&mb_strlen($password)<8)$errors[]='La contraseña debe tener al menos 8 caracteres.';
   if(!$errors){$emailQ=$pdo->prepare('SELECT 1 FROM users_data WHERE email=? AND idUser<>?');$emailQ->execute([$data['email'],$id]);if($emailQ->fetchColumn())$errors[]='El email ya está en uso.';if($action==='create'){$userQ=$pdo->prepare('SELECT 1 FROM users_login WHERE usuario=?');$userQ->execute([$usuario]);if($userQ->fetchColumn())$errors[]='El nombre de usuario ya existe.';}}
   if(!$errors)try{$pdo->beginTransaction();if($action==='create'){$pdo->prepare('INSERT INTO users_data (nombre,apellidos,email,telefono,fecha_nacimiento,direccion,sexo) VALUES (?,?,?,?,?,?,?)')->execute(array_values($data));$id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO users_login (idUser,usuario,password,rol) VALUES (?,?,?,?)')->execute([$id,$usuario,password_hash($password,PASSWORD_DEFAULT),$rol]);}elseif($action==='update'){$pdo->prepare('UPDATE users_data SET nombre=?,apellidos=?,email=?,telefono=?,fecha_nacimiento=?,direccion=?,sexo=? WHERE idUser=?')->execute([...array_values($data),$id]);$pdo->prepare('UPDATE users_login SET rol=? WHERE idUser=?')->execute([$rol,$id]);}else throw new RuntimeException();$pdo->commit();flash($action==='create'?'Usuario creado.':'Usuario actualizado.');header('Location: usuarios-administracion.php');exit;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]='No se ha podido guardar el usuario.';}
  }
 }
}
$users=$pdo->query('SELECT d.*,l.usuario,l.rol FROM users_data d INNER JOIN users_login l ON l.idUser=d.idUser ORDER BY d.apellidos,d.nombre')->fetchAll();$editId=(int)($_GET['editar']??0);$editing=null;foreach($users as $u)if($u['idUser']===$editId)$editing=$u;
$tituloPagina='Administración de usuarios | Salud Cercana';require __DIR__.'/includes/header.php';
?>
<main class="content-section"><div class="section-heading"><p class="eyebrow">Administración</p><h1>Usuarios</h1><p>El nombre de usuario se conserva para evitar romper el acceso existente.</p></div><?php if($message):?><p class="alert alert-success"><?=e($message)?></p><?php endif;?><?php foreach($errors as $error):?><p class="alert alert-error"><?=e($error)?></p><?php endforeach;?>
<section class="form-card"><h2><?=$editing?'Editar usuario':'Crear usuario'?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken('admin_users'))?>"><input type="hidden" name="action" value="<?=$editing?'update':'create'?>"><?php if($editing):?><input type="hidden" name="idUser" value="<?=$editing['idUser']?>"><?php endif;?><div class="form-grid"><?php foreach(['nombre'=>'Nombre','apellidos'=>'Apellidos','email'=>'Email','telefono'=>'Teléfono','fecha_nacimiento'=>'Fecha nacimiento','direccion'=>'Dirección'] as $f=>$label):?><label<?= $f==='direccion'?' class="full-width"':''?>><?=$label?><input name="<?=$f?>" <?=$f==='email'?'type="email"':($f==='fecha_nacimiento'?'type="date"':'')?> required value="<?=e($editing[$f]??'')?>"></label><?php endforeach;?><label>Sexo<select name="sexo" required><?php foreach(['femenino'=>'Femenino','masculino'=>'Masculino','otro'=>'Otro'] as $v=>$l):?><option value="<?=$v?>" <?=($editing['sexo']??'')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></label><label>Rol<select name="rol"><option value="user" <?=($editing['rol']??'user')==='user'?'selected':''?>>Usuario</option><option value="admin" <?=($editing['rol']??'')==='admin'?'selected':''?>>Administrador</option></select></label><?php if($editing):?><label>Usuario<input readonly value="<?=e($editing['usuario'])?>"></label><?php else:?><label>Usuario<input name="usuario" required></label><label>Contraseña<input name="password" type="password" minlength="8" required></label><?php endif;?></div><button class="button"><?=$editing?'Guardar cambios':'Crear usuario'?></button><?php if($editing):?> <a href="usuarios-administracion.php">Cancelar</a><?php endif;?></form></section>
<section class="spaced-card"><h2>Usuarios registrados</h2><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Email</th><th>Usuario</th><th>Rol</th><th>Acciones</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?=e($u['nombre'].' '.$u['apellidos'])?></td><td><?=e($u['email'])?></td><td><?=e($u['usuario'])?></td><td><?=e($u['rol'])?></td><td><a href="usuarios-administracion.php?editar=<?=$u['idUser']?>">Editar</a><?php if($u['idUser']!==$adminId):?><form class="inline-form" method="post" onsubmit="return confirm('¿Eliminar este usuario? Solo se permite si no tiene citas ni noticias.');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken('admin_users'))?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="idUser" value="<?=$u['idUser']?>"><button class="link-button">Eliminar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section></main><?php require __DIR__.'/includes/footer.php'; ?>
