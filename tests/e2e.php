<?php
declare(strict_types=1);
require_once __DIR__ . '/e2e_setup.php';

$checks = 0;
function e2eCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function e2eToken(array $response): string
{
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $response['body'], $match);
    e2eCheck(isset($match[1]), 'Expected a CSRF token in the rendered form.');
    return $match[1];
}
function e2eSessionExists(PDO $pdo, string $cookie): bool
{
    $query = $pdo->prepare('SELECT 1 FROM app_sessions WHERE session_key = ?');
    $query->execute([hash('sha256', $cookie)]);
    return $query->fetchColumn() !== false;
}
function e2eNews(PDO $pdo, string $title): array|false
{
    $query = $pdo->prepare('SELECT * FROM noticias WHERE titulo = ?');
    $query->execute([$title]);
    return $query->fetch();
}
function e2eImageCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM app_news_images')->fetchColumn();
}
final class E2eBrowser
{
    public array $cookies = [];

    public function request(string $base, string $path, string $method = 'GET', ?array $form = null,
        bool $multipart = false, array $requestHeaders = []): array
    {
        $curl = curl_init($base . $path);
        $headers = [];
        $rawHeaders = [];
        $cookieHeader = [];
        foreach ($this->cookies as $name => $value) $cookieHeader[] = $name . '=' . $value;
        if ($cookieHeader) $requestHeaders[] = 'Cookie: ' . implode('; ', $cookieHeader);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_PATH_AS_IS => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$headers, &$rawHeaders): int {
                $rawHeaders[] = trim($line);
                $pair = explode(':', $line, 2);
                if (count($pair) === 2) {
                    $name = strtolower(trim($pair[0]));
                    $value = trim($pair[1]);
                    $headers[$name] = $value;
                    if ($name === 'set-cookie') {
                        $cookie = explode('=', explode(';', $value, 2)[0], 2);
                        if (count($cookie) === 2) $this->cookies[$cookie[0]] = $cookie[1];
                    }
                }
                return strlen($line);
            },
        ]);
        if ($form !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $form : http_build_query($form));
        if ($method === 'HEAD') curl_setopt($curl, CURLOPT_NOBODY, true);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $failed = $body === false;
        curl_close($curl);
        e2eCheck(!$failed, 'An HTTP request failed before receiving a response.');
        return ['status' => $status, 'body' => (string) $body, 'headers' => $headers, 'raw_headers' => $rawHeaders];
    }
}

function e2eLogin(E2eBrowser $browser, PDO $pdo, string $base, string $username, string $password): void
{
    $page = $browser->request($base, '/login.php');
    e2eCheck($page['status'] === 200, 'Login form must render.');
    $token = e2eToken($page);
    $oldCookie = $browser->cookies['salud_session'] ?? '';
    e2eCheck($oldCookie !== '', 'A login form must issue a session cookie.');
    $response = $browser->request($base, '/login.php', 'POST', [
        'csrf_token' => $token, 'usuario' => $username, 'password' => $password,
    ]);
    e2eCheck($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'index.php', 'Valid credentials must log in.');
    $newCookie = $browser->cookies['salud_session'] ?? '';
    e2eCheck($newCookie !== '' && $newCookie !== $oldCookie, 'Login must regenerate the session ID.');
    e2eCheck(!e2eSessionExists($pdo, $oldCookie) && e2eSessionExists($pdo, $newCookie), 'Only the regenerated authenticated session may remain.');
}

$baseA = $argv[1] ?? '';
$baseB = $argv[2] ?? '';
foreach ([$baseA, $baseB] as $base) {
    if (preg_match('~\Ahttp://127\.0\.0\.1:[0-9]{1,5}\z~D', $base) !== 1) {
        throw new RuntimeException('E2E accepts only two explicit loopback HTTP servers.');
    }
}
e2eCheck($baseA !== $baseB, 'Use two distinct HTTP servers for persistence checks.');
$pdo = e2ePrepareSchema();
$runId = bin2hex(random_bytes(6));
$username = 'e2e_user_' . $runId;
$adminName = 'e2e_admin_' . $runId;
$password = bin2hex(random_bytes(16));
$adminPassword = bin2hex(random_bytes(16));
$email = $username . '@example.invalid';
$title = 'E2E fixture image ' . $runId;
$secondTitle = 'E2E fixture alternate ' . $runId;
$createdTitle = 'E2E fixture create page ' . $runId;
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
$imageFile = tempnam(sys_get_temp_dir(), 'salud-e2e-image-');
if ($imageFile === false) throw new RuntimeException('Could not create a synthetic fixture.');
file_put_contents($imageFile, $png);
try {
    $anonymous = new E2eBrowser();
    foreach (['/config/environment.php', '/config/conexion.php', '/includes/auth.php', '/.env', '/.git/config',
        '/sitio_web.sql', '/README.md', '/tests/e2e.php', '/api/index.php',
        '/uploads/noticias/%2e%2e/%2e%2e/config/environment.php', '/%2e%2e/config/conexion.php'] as $internal) {
        $response = $anonymous->request($baseA, $internal);
        e2eCheck($response['status'] === 404 && !str_contains($response['body'], '<?php'), 'The router must conceal internal files and traversal paths.');
    }
    foreach (['/perfil.php', '/noticias-administracion.php', '/crear_noticia.php'] as $protected) {
        $response = $anonymous->request($baseA, $protected);
        e2eCheck($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'login.php', 'Anonymous access must redirect to login.');
    }
    $oldStatic = glob(__DIR__ . '/../uploads/noticias/*.webp')[0] ?? null;
    e2eCheck(is_string($oldStatic), 'A historical static fixture must exist.');
    $static = $anonymous->request($baseB, '/uploads/noticias/' . basename($oldStatic));
    e2eCheck($static['status'] === 200 && hash('sha256', $static['body']) === hash_file('sha256', $oldStatic), 'Historical repository images must remain available.');
    e2eCheck(!isset($static['headers']['set-cookie']), 'Static images must not initiate a session.');

    $user = new E2eBrowser();
    $registration = $user->request($baseA, '/registro.php');
    $registrationToken = e2eToken($registration);
    $cookiePolicy = strtolower($registration['headers']['set-cookie'] ?? '');
    e2eCheck(str_contains($cookiePolicy, 'httponly') && str_contains($cookiePolicy, 'samesite=lax'), 'Session cookies must use HttpOnly and SameSite.');
    $form = ['nombre' => 'Fixture', 'apellidos' => 'Synthetic', 'email' => $email,
        'telefono' => '600123456', 'fecha_nacimiento' => '1990-01-01', 'direccion' => 'Fixture address',
        'sexo' => 'otro', 'usuario' => $username, 'password' => $password, 'rol' => 'admin'];
    $response = $user->request($baseA, '/registro.php', 'POST', $form + ['csrf_token' => 'invalid']);
    e2eCheck($response['status'] === 200 && str_contains($response['body'], 'solicitud no es válida'), 'Registration must reject an invalid CSRF token.');
    $findUser = $pdo->prepare('SELECT l.*, d.email FROM users_login l JOIN users_data d ON d.idUser = l.idUser WHERE usuario = ?');
    $findUser->execute([$username]);
    e2eCheck($findUser->fetch() === false, 'Rejected registration must not create an account.');
    $response = $user->request($baseA, '/registro.php', 'POST', $form + ['csrf_token' => $registrationToken]);
    e2eCheck($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'login.php', 'Valid registration must succeed.');
    $findUser->execute([$username]);
    $registered = $findUser->fetch();
    e2eCheck($registered && $registered['rol'] === 'user' && password_verify($password, $registered['password']), 'Registration must hash passwords and ignore submitted admin roles.');
    $loginPage = $user->request($baseA, '/login.php');
    $loginToken = e2eToken($loginPage);
    $response = $user->request($baseA, '/login.php', 'POST', ['csrf_token' => 'invalid', 'usuario' => $username, 'password' => $password]);
    e2eCheck($response['status'] === 200 && str_contains($response['body'], 'solicitud no es válida'), 'Login must reject an invalid CSRF token.');
    $response = $user->request($baseA, '/login.php', 'POST', ['csrf_token' => $loginToken, 'usuario' => $username, 'password' => 'wrong-fixture-password']);
    e2eCheck($response['status'] === 200 && str_contains($response['body'], 'incorrectos'), 'Invalid credentials must not authenticate.');
    e2eLogin($user, $pdo, $baseA, $username, $password);
    $profile = $user->request($baseB, '/perfil.php');
    e2eCheck($profile['status'] === 200 && str_contains($profile['body'], $email), 'An authenticated session must survive a different PHP process.');
    foreach (['/usuarios-administracion.php', '/citaciones-administracion.php', '/noticias-administracion.php', '/crear_noticia.php'] as $adminOnly) {
        $response = $user->request($baseB, $adminOnly);
        e2eCheck($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'index.php', 'An ordinary user must be denied every administrator page.');
    }
    $response = $user->request($baseA, '/crear_noticia.php', 'POST', ['titulo' => $title, 'csrf_token' => 'invalid']);
    e2eCheck($response['status'] === 302 && e2eNews($pdo, $title) === false, 'An ordinary user must not publish directly.');

    // Directly create only this run's synthetic administrator in the guarded DB.
    $pdo->beginTransaction();
    $query = $pdo->prepare('INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_nacimiento, direccion, sexo) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $query->execute(['Fixture', 'Administrator', $adminName . '@example.invalid', '600123456', '1990-01-01', 'Fixture address', 'otro']);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO users_login (idUser, usuario, password, rol) VALUES (?, ?, ?, ?)')->execute([$adminId, $adminName, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
    $pdo->commit();
    $admin = new E2eBrowser();
    e2eLogin($admin, $pdo, $baseA, $adminName, $adminPassword);
    $editor = $admin->request($baseB, '/noticias-administracion.php');
    e2eCheck($editor['status'] === 200, 'An administrator must open the editor from another process.');
    $newsToken = e2eToken($editor);
    $newsForm = ['action' => 'create', 'titulo' => $title, 'texto' => 'Synthetic fixture news.', 'fecha' => date('Y-m-d'),
        'imagen' => new CURLFile($imageFile, 'image/jpeg', 'fixture.jpg')];
    $imagesBefore = e2eImageCount($pdo);
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', $newsForm + ['csrf_token' => 'invalid'], true);
    e2eCheck($response['status'] === 200 && e2eImageCount($pdo) === $imagesBefore && e2eNews($pdo, $title) === false, 'Rejected CSRF must not write an image or a news row.');
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', ['csrf_token' => $newsToken, 'action' => 'unsupported']);
    e2eCheck($response['status'] === 200 && str_contains($response['body'], 'acción no es válida') && e2eImageCount($pdo) === $imagesBefore, 'Unknown news actions must fail without writes.');
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', $newsForm + ['csrf_token' => $newsToken], true);
    $news = e2eNews($pdo, $title);
    e2eCheck($response['status'] === 302 && $news && e2eImageCount($pdo) === $imagesBefore + 1, 'A valid multipart news upload must commit one image and one news row.');
    $imagePath = $news['imagen'];
    $publicImage = (new E2eBrowser())->request($baseB, '/' . $imagePath);
    e2eCheck($publicImage['status'] === 200 && $publicImage['body'] === $png && ($publicImage['headers']['content-type'] ?? '') === 'image/png', 'Image bytes and detected MIME must persist across processes despite a false browser MIME.');
    e2eCheck(!isset($publicImage['headers']['set-cookie']) && str_contains($publicImage['headers']['cache-control'] ?? '', 'immutable'), 'Public images must remain session-free and cacheable.');
    $headImage = (new E2eBrowser())->request($baseB, '/' . $imagePath, 'HEAD');
    e2eCheck($headImage['status'] === 200 && $headImage['body'] === '' && (int) ($headImage['headers']['content-length'] ?? 0) === strlen($png), 'Image HEAD must return metadata without bytes.');
    $cachedImage = (new E2eBrowser())->request($baseA, '/' . $imagePath, 'GET', null, false, ['If-None-Match: ' . $publicImage['headers']['etag']]);
    e2eCheck($cachedImage['status'] === 304 && $cachedImage['body'] === '', 'A matching ETag must return 304.');
    $response = $admin->request($baseB, '/noticias-administracion.php', 'POST', $newsForm + ['csrf_token' => $newsToken], true);
    e2eCheck($response['status'] === 200 && e2eImageCount($pdo) === $imagesBefore + 1 && e2eNews($pdo, $title)['imagen'] === $imagePath, 'A duplicate news title must roll back only the new upload.');
    $invalidNews = $newsForm;
    $invalidNews['titulo'] = '';
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', $invalidNews + ['csrf_token' => $newsToken], true);
    e2eCheck($response['status'] === 200 && e2eImageCount($pdo) === $imagesBefore + 1, 'Invalid text fields must be checked before storing an image.');
    file_put_contents($imageFile, $png . str_repeat('x', 2 * 1024 * 1024 + 1 - strlen($png)));
    $oversizedNews = $newsForm;
    $oversizedNews['titulo'] = 'E2E oversized fixture ' . $runId;
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', $oversizedNews + ['csrf_token' => $newsToken], true);
    e2eCheck($response['status'] === 200 && e2eImageCount($pdo) === $imagesBefore + 1 && e2eNews($pdo, $oversizedNews['titulo']) === false, 'An image exceeding 2 MiB must fail without a BLOB or news row.');
    file_put_contents($imageFile, $png);

    $updateForm = ['csrf_token' => $newsToken, 'action' => 'update', 'idNoticia' => $news['idNoticia'],
        'titulo' => $secondTitle, 'texto' => 'Updated synthetic fixture.', 'fecha' => date('Y-m-d')];
    $response = $admin->request($baseB, '/noticias-administracion.php', 'POST', $updateForm);
    e2eCheck($response['status'] === 302 && e2eNews($pdo, $secondTitle)['imagen'] === $imagePath, 'Editing text must retain the previous image.');
    $updateForm['imagen'] = new CURLFile($imageFile, 'image/png', 'replacement.png');
    $response = $admin->request($baseA, '/noticias-administracion.php', 'POST', $updateForm, true);
    $replacementPath = e2eNews($pdo, $secondTitle)['imagen'];
    e2eCheck($response['status'] === 302 && $replacementPath !== $imagePath && e2eImageCount($pdo) === $imagesBefore + 2, 'Replacing an image must create a new immutable URL and retain the old BLOB.');
    $response = $admin->request($baseB, '/noticias-administracion.php', 'POST', ['csrf_token' => $newsToken, 'action' => 'delete', 'idNoticia' => $news['idNoticia']]);
    e2eCheck($response['status'] === 302 && e2eNews($pdo, $secondTitle) === false && e2eImageCount($pdo) === $imagesBefore + 2, 'Deleting this synthetic news row must retain both historical images.');
    e2eCheck((new E2eBrowser())->request($baseA, '/' . $imagePath)['body'] === $png, 'A historical public image URL must still work after deleting its news.');

    $createPage = $admin->request($baseA, '/crear_noticia.php');
    $createToken = e2eToken($createPage);
    $response = $admin->request($baseB, '/crear_noticia.php', 'POST', [
        'csrf_token' => $createToken, 'titulo' => $createdTitle, 'texto' => 'Synthetic fixture from alternate form.',
        'fecha' => date('Y-m-d'), 'imagen' => new CURLFile($imageFile, 'image/png', 'fixture.png'),
    ], true);
    e2eCheck($response['status'] === 302 && e2eNews($pdo, $createdTitle) !== false, 'The separate create-news form must also persist uploads.');
    $publicNews = $anonymous->request($baseB, '/noticias.php');
    e2eCheck($publicNews['status'] === 200 && str_contains($publicNews['body'], $createdTitle), 'Published news must render publicly across processes.');

    // Changing only our own fixture role must revoke cached session privileges.
    $pdo->prepare('UPDATE users_login SET rol = ? WHERE idUser = ?')->execute(['user', $adminId]);
    $response = $admin->request($baseB, '/noticias-administracion.php');
    e2eCheck($response['status'] === 302 && ($response['headers']['location'] ?? '') === 'index.php', 'Role revocation must take effect on the next request.');
    $pdo->prepare('UPDATE users_login SET rol = ? WHERE idUser = ?')->execute(['admin', $adminId]);

    $logout = $user->request($baseB, '/logout.php');
    $logoutToken = e2eToken($logout);
    $response = $user->request($baseA, '/logout.php', 'POST', ['csrf_token' => 'invalid']);
    e2eCheck($response['status'] === 403, 'Logout must reject an invalid CSRF token.');
    e2eCheck($user->request($baseB, '/perfil.php')['status'] === 200, 'Rejected logout must retain the authenticated session.');
    $oldCookie = $user->cookies['salud_session'];
    $response = $user->request($baseA, '/logout.php', 'POST', ['csrf_token' => $logoutToken]);
    e2eCheck($response['status'] === 302 && $user->cookies['salud_session'] !== $oldCookie && !e2eSessionExists($pdo, $oldCookie), 'Logout must invalidate and regenerate the session ID.');
    e2eCheck($user->request($baseB, '/perfil.php')['status'] === 302, 'Logged-out sessions must not authenticate in another process.');
    $replay = new E2eBrowser();
    $replay->cookies['salud_session'] = $oldCookie;
    e2eCheck($replay->request($baseB, '/perfil.php')['status'] === 302, 'Replaying a destroyed authenticated session must fail.');
    echo "E2E: {$checks} checks passed against two isolated local PHP processes and MySQL.\n";
    echo "Only synthetic fixture rows were created; credentials and cookies were not printed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_file($imageFile)) unlink($imageFile);
}
