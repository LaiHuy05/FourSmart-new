<?php

final class AccountModel
{
    public static function all(): array
    {
        return Database::query(
            'SELECT * FROM account'
        );
    }

    public static function find(int $id)
    {
        return Database::one(
            'SELECT * FROM account
             WHERE tk_id = ?',
            $id
        );
    }

    public static function findByUsername(
        string $username
    ) {
        return Database::one(
            'SELECT * FROM account
             WHERE tk_user = ?
             LIMIT 1',
            $username
        );
    }
    public static function usernameExists(
      string $username
  ): bool {
      $result = Database::one(
          'SELECT COUNT(*) AS total
           FROM account
           WHERE tk_user = ?',
          $username
      );
  
      return (int) ($result['total'] ?? 0) > 0;
  }
  
  public static function emailExists(
      string $email
  ): bool {
      $result = Database::one(
          'SELECT COUNT(*) AS total
           FROM account
           WHERE tk_email = ?',
          $email
      );
  
      return (int) ($result['total'] ?? 0) > 0;
  }
  
  public static function create(
      string $username,
      string $password,
      string $email,
      string $address,
      int $roleId = 2
  ): void {
      Database::execute(
          'INSERT INTO account(
              tk_user,
              tk_password,
              tk_email,
              tk_address,
              id_role
           )
           VALUES (?, ?, ?, ?, ?)',
          $username,
          password_hash(
              $password,
              PASSWORD_BCRYPT
          ),
          $email,
          $address,
          $roleId
      );
  }
  public static function verifyPassword(
    array $account,
    string $password
): bool {
    $storedPassword = (string) ($account['tk_password'] ?? '');

    if ($storedPassword === '') {
        return false;
    }

    if (password_verify($password, $storedPassword)) {
        return true;
    }

    // Hỗ trợ tạm tài khoản cũ còn lưu mật khẩu thường
    if (hash_equals($storedPassword, $password)) {
        $newHash = password_hash(
            $password,
            PASSWORD_BCRYPT
        );

        Database::execute(
            'UPDATE account
             SET tk_password = ?
             WHERE tk_id = ?',
            $newHash,
            (int) $account['tk_id']
        );

        return true;
    }

    return false;
}
}