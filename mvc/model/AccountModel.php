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

    // Never treat a stored password hash as a legacy plaintext password.
    if (
        password_get_info($storedPassword)['algoName'] !== 'unknown'
        || preg_match('/^\$(?:2[abxy]\$|argon2(?:i|id)\$)/', $storedPassword)
    ) {
        return false;
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
public static function emailExistsForOtherUser(
  string $email,
  int $userId
): bool {
  $result = Database::one(
      'SELECT COUNT(*) AS total
       FROM account
       WHERE tk_email = ?
       AND tk_id <> ?',
      $email,
      $userId
  );

  return (int) ($result['total'] ?? 0) > 0;
}

public static function updateProfile(
  int $id,
  string $email,
  string $address
): void {
  Database::execute(
      'UPDATE account
       SET tk_email = ?,
           tk_address = ?
       WHERE tk_id = ?',
      $email,
      $address,
      $id
  );
}
public static function roles(): array
{
    return Database::query(
        'SELECT * FROM role'
    );
}

public static function usernameExistsForOtherUser(
    string $username,
    int $userId
): bool {
    $result = Database::one(
        'SELECT COUNT(*) AS total
         FROM account
         WHERE tk_user = ?
         AND tk_id <> ?',
        $username,
        $userId
    );

    return (int) ($result['total'] ?? 0) > 0;
}

public static function updateAdmin(
    int $id,
    string $username,
    string $email,
    string $address,
    int $roleId
): void {
    Database::execute(
        'UPDATE account
         SET tk_user = ?,
             tk_email = ?,
             tk_address = ?,
             id_role = ?
         WHERE tk_id = ?',
        $username,
        $email,
        $address,
        $roleId,
        $id
    );
}

public static function resetCustomerPassword(
    int $id,
    #[\SensitiveParameter] string $password
): bool {
    $statement = Database::connection()->prepare(
        'UPDATE account
         SET tk_password = ?
         WHERE tk_id = ? AND id_role = 2'
    );

    $statement->execute([
        password_hash($password, PASSWORD_BCRYPT),
        $id,
    ]);

    // The role condition also protects a target promoted after validation.
    return $statement->rowCount() === 1;
}

public static function delete(int $id): void
{
    Database::execute(
        'DELETE FROM account
         WHERE tk_id = ?',
        $id
    );
}
public static function hasRelatedData(int $id): bool
{
    $cart = Database::one(
        'SELECT COUNT(*) AS total
         FROM cart
         WHERE id_tk = ?',
        $id
    );

    $orders = Database::one(
        'SELECT COUNT(*) AS total
         FROM `order`
         WHERE id_tk = ?',
        $id
    );

    $comments = Database::one(
        'SELECT COUNT(*) AS total
         FROM comment
         WHERE id_tk = ?',
        $id
    );

    return
        (int) ($cart['total'] ?? 0) > 0
        || (int) ($orders['total'] ?? 0) > 0
        || (int) ($comments['total'] ?? 0) > 0;
}
}
