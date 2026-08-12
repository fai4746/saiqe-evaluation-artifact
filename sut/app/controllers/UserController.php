<?php
class UserController {
    private UserModel $users;
    private AuditLogModel $audit;

    public function __construct() {
        $this->users = new UserModel();
        $this->audit = new AuditLogModel();
    }

    public function list(): void {
        Auth::requireRole(ROLE_ADMIN);
        $roleFilter = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
        $users  = $this->users->getAll($roleFilter);
        $errors = Session::get('user_errors', []);
        Session::delete('user_errors');
        include APP_ROOT . '/app/views/admin/user_management.php';
    }

    public function create(): void {
        Auth::requireRole(ROLE_ADMIN);
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/users'); exit;
        }

        $errors = [];
        $email = trim($_POST['email'] ?? '');
        if (empty($_POST['first_name'])) $errors['first_name'] = 'Required.';
        if (empty($_POST['last_name']))  $errors['last_name']  = 'Required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email required.';
        if (!in_array((int)$_POST['role_id'], [ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_AUDITOR])) $errors['role_id'] = 'Invalid role.';
        if (empty($_POST['password']) || strlen($_POST['password']) < 8) $errors['password'] = 'Min 8 characters.';

        if (!$errors && $this->users->findByEmail($email)) $errors['email'] = 'Email already exists.';

        if ($errors) {
            Session::set('user_errors', $errors);
            header('Location: ' . BASE_URL . '/users'); exit;
        }

        $newId = $this->users->create(['role_id'=>(int)$_POST['role_id'],'first_name'=>trim($_POST['first_name']),'last_name'=>trim($_POST['last_name']),'email'=>$email,'password'=>$_POST['password']]);
        $this->audit->log(['action_type'=>'User Created','entity_type'=>'user','entity_id'=>$newId,'new_value'=>['email'=>$email,'role_id'=>$_POST['role_id']]]);
        Session::set('user_success', 'User created successfully.');
        header('Location: ' . BASE_URL . '/users'); exit;
    }

    public function update(string $id): void {
        Auth::requireRole(ROLE_ADMIN);
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/users'); exit;
        }
        $data = array_filter(['first_name'=>$_POST['first_name']??null,'last_name'=>$_POST['last_name']??null,'status'=>$_POST['status']??null,'role_id'=>$_POST['role_id']??null]);
        $this->users->update((int)$id, $data);
        $this->audit->log(['action_type'=>'User Updated','entity_type'=>'user','entity_id'=>$id,'new_value'=>$data]);
        header('Location: ' . BASE_URL . '/users'); exit;
    }

    public function deactivate(string $id): void {
        Auth::requireRole(ROLE_ADMIN);
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/users'); exit;
        }
        $this->users->update((int)$id, ['status'=>'inactive']);
        $this->audit->log(['action_type'=>'User Deactivated','entity_type'=>'user','entity_id'=>$id]);
        header('Location: ' . BASE_URL . '/users'); exit;
    }

    public function profile(): void {
        Auth::requireLogin();
        $user   = $this->users->findById(Session::get('user_id'));
        $errors = Session::get('profile_errors', []);
        $success = Session::get('profile_success');
        Session::delete('profile_errors'); Session::delete('profile_success');
        include APP_ROOT . '/app/views/shared/profile.php';
    }

    public function updateProfile(): void {
        Auth::requireLogin();
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/profile'); exit;
        }
        $userId = Session::get('user_id');
        $data   = [];
        if (!empty($_POST['first_name'])) $data['first_name'] = trim($_POST['first_name']);
        if (!empty($_POST['last_name']))  $data['last_name']  = trim($_POST['last_name']);
        if (!empty($_POST['password']) && strlen($_POST['password']) >= 8 && $_POST['password'] === ($_POST['confirm_password']??'')) {
            $data['password'] = $_POST['password'];
        }
        $this->users->update($userId, $data);
        Session::set('profile_success', 'Profile updated.');
        header('Location: ' . BASE_URL . '/profile'); exit;
    }
}
