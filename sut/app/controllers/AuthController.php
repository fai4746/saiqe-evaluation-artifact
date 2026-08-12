<?php
class AuthController {
    private UserModel $users;
    private AuditLogModel $audit;

    public function __construct() {
        $this->users = new UserModel();
        $this->audit = new AuditLogModel();
    }

    public function showLogin(): void {
        if (Auth::isLoggedIn()) $this->redirectToDashboard();
        $error = Session::get('login_error');
        Session::delete('login_error');
        include APP_ROOT . '/app/views/shared/login.php';
    }

    public function processLogin(): void {
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::set('login_error', 'Invalid request.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            Session::set('login_error', 'Email and password are required.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        $user = $this->users->findByEmail($email);

        if (!$user) {
            $this->audit->log(['action_type'=>'Failed Login','entity_type'=>'user','new_value'=>['email'=>$email],'user_id'=>null,'username'=>$email,'role'=>null]);
            Session::set('login_error', 'Invalid credentials. Please try again.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        if ($user['status'] === 'locked') {
            Session::set('login_error', 'Account is locked. Contact administrator.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        if ($user['status'] === 'inactive') {
            Session::set('login_error', 'Account is inactive. Contact administrator.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->users->incrementFailedLogins($user['user_id']);
            if (Auth::shouldLockAccount((int)$user['failed_logins'])) {
                $this->users->lockAccount($user['user_id']);
            }
            $this->audit->log(Auth::failedLoginRecord($email, $user, $password));
            Session::set('login_error', 'Invalid credentials. Please try again.');
            header('Location: ' . BASE_URL . '/login'); exit;
        }

        $this->users->resetFailedLogins($user['user_id']);
        Auth::login($user);
        $this->audit->log(['action_type'=>'Login','entity_type'=>'user','entity_id'=>$user['user_id'],'user_id'=>$user['user_id'],'username'=>$user['email'],'role'=>$user['role_name']]);
        $this->redirectToDashboard();
    }

    public function logout(): void {
        if (Auth::isLoggedIn()) {
            $user = Auth::user();
            $this->audit->log(['action_type'=>'Logout','entity_type'=>'user','entity_id'=>$user['user_id']]);
        }
        Auth::logout();
        header('Location: ' . BASE_URL . '/login'); exit;
    }

    public function showRegister(): void {
        if (Auth::isLoggedIn()) $this->redirectToDashboard();
        $errors = Session::get('reg_errors', []);
        $old    = Session::get('reg_old', []);
        Session::delete('reg_errors'); Session::delete('reg_old');
        include APP_ROOT . '/app/views/shared/register.php';
    }

    public function processRegister(): void {
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/register'); exit;
        }

        $data   = $_POST;
        $errors = $this->validateRegistration($data);

        // Check duplicate email
        if (empty($errors['email']) && $this->users->findByEmail(trim($data['email']))) {
            $errors['email'] = 'This email address is already registered.';
        }

        if ($errors) {
            Session::set('reg_errors', $errors);
            Session::set('reg_old', $data);
            header('Location: ' . BASE_URL . '/register'); exit;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $userId = $this->users->create(['role_id'=>ROLE_CITIZEN,'first_name'=>trim($data['first_name']),'last_name'=>trim($data['last_name']),'email'=>trim($data['email']),'password'=>$data['password']]);
            (new CitizenModel())->create($userId, $data);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            Session::set('reg_errors', ['general' => 'Registration failed. Please try again.']);
            header('Location: ' . BASE_URL . '/register'); exit;
        }

        Session::set('reg_success', 'Registration successful! Please log in.');
        $this->audit->log(['action_type'=>'Registration','entity_type'=>'user','new_value'=>['email'=>trim($data['email'])],'user_id'=>null,'username'=>trim($data['email']),'role'=>'citizen']);
        header('Location: ' . BASE_URL . '/login'); exit;
    }

    private function validateRegistration(array $d): array {
        return IntakeValidator::validateRegistration($d);
    }

    private function redirectToDashboard(): void {
        $roleId = Session::get('role_id');
        $path   = ROLE_DASHBOARDS[$roleId] ?? '/dashboard/citizen';
        header('Location: ' . BASE_URL . $path); exit;
    }
}
