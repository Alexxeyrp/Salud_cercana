<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php'; requireLogin(); require_once __DIR__ . '/config/conexion.php';
$idUser=(int)$_SESSION['idUser']; $errors=[]; $message=$_SESSION['flash']??''; unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrfValid('citaciones', $_POST['csrf_token']??null)) $errors[]='La solicitud no es válida.';
    else {
        $action=$_POST['action']??''; $id=(int)($_POST['idCita']??0); $fecha=trim((string)($_POST['fecha_cita']??'')); $motivo=trim((string)($_POST['motivo_cita']??''));
        if ($action==='create' || $action==='update') {
            if (!validDate($fecha)) $errors[]='La fecha debe ser hoy o posterior y válida.';
            if ($motivo==='') $errors[]='El motivo es obligatorio.';
            if (mb_strlen($motivo)>5000) $errors[]='El motivo es demasiado largo.';
        }
        if (($action==='update'||$action==='delete') && !$errors) {
            $own=$pdo->prepare('SELECT idCita FROM citas WHERE idCita=? AND idUser=? AND fecha_cita>=CURDATE()'); $own->execute([$id,$idUser]);
            if (!$own->fetch()) $errors[]='La cita no existe, no te pertenece o ya ha pasado.';
        }
        if (!$errors && $action==='create') { $pdo->prepare('INSERT INTO citas (idUser,fecha_cita,motivo_cita) VALUES (?,?,?)')->execute([$idUser,$fecha,$motivo]); flash('Cita solicitada correctamente.'); header('Location: citaciones.php'); exit; }
        if (!$errors && $action==='update') { $pdo->prepare('UPDATE citas SET fecha_cita=?, motivo_cita=? WHERE idCita=? AND idUser=?')->execute([$fecha,$motivo,$id,$idUser]); flash('Cita actualizada.'); header('Location: citaciones.php'); exit; }
        if (!$errors && $action==='delete') { $pdo->prepare('DELETE FROM citas WHERE idCita=? AND idUser=?')->execute([$id,$idUser]); flash('Cita eliminada.'); header('Location: citaciones.php'); exit; }
    }
}
$q=$pdo->prepare('SELECT idCita, fecha_cita, motivo_cita FROM citas WHERE idUser=? ORDER BY fecha_cita ASC'); $q->execute([$idUser]); $citas=$q->fetchAll();
$editId=(int)($_GET['editar']??0); $editing=null; foreach($citas as $cita) if($cita['idCita']===$editId && $cita['fecha_cita']>=date('Y-m-d')) $editing=$cita;
$tituloPagina='Mis citaciones | Salud Cercana'; require __DIR__.'/includes/header.php';
?>
<main class="content-section"><div class="section-heading"><p class="eyebrow">Atención</p><h1>Mis citaciones</h1></div><?php if($message):?><p class="alert alert-success"><?=e($message)?></p><?php endif;?><?php foreach($errors as $error):?><p class="alert alert-error"><?=e($error)?></p><?php endforeach;?>
<section class="form-card"><h2><?= $editing?'Editar cita':'Solicitar una cita' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken('citaciones'))?>"><input type="hidden" name="action" value="<?=$editing?'update':'create'?>"><?php if($editing):?><input type="hidden" name="idCita" value="<?=$editing['idCita']?>"><?php endif;?><div class="form-grid"><label>Fecha<input name="fecha_cita" type="date" min="<?=date('Y-m-d')?>" required value="<?=e($editing['fecha_cita']??'')?>"></label><label class="full-width">Motivo<textarea name="motivo_cita" required><?=e($editing['motivo_cita']??'')?></textarea></label></div><button class="button"><?=$editing?'Guardar cambios':'Solicitar cita'?></button><?php if($editing):?> <a href="citaciones.php">Cancelar</a><?php endif;?></form></section>
<section class="spaced-card"><h2>Historial de citas</h2><?php if(!$citas):?><p class="empty-state">Todavía no tienes citas.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($citas as $cita):$modifiable=$cita['fecha_cita']>=date('Y-m-d');?><tr><td><?=e(date('d/m/Y',strtotime($cita['fecha_cita'])))?></td><td><?=e($cita['motivo_cita'])?></td><td><?= $modifiable?'Pendiente / modificable':'Fecha pasada' ?></td><td><?php if($modifiable):?><a href="citaciones.php?editar=<?=$cita['idCita']?>">Editar</a><form class="inline-form" method="post" onsubmit="return confirm('¿Eliminar esta cita?');"><input type="hidden" name="csrf_token" value="<?=e(csrfToken('citaciones'))?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="idCita" value="<?=$cita['idCita']?>"><button class="link-button">Eliminar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></main><?php require __DIR__.'/includes/footer.php'; ?>
