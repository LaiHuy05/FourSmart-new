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
        #[\SensitiveParameter] array $account,
        #[\SensitiveParameter] string $password
    ): bool {
        $stored = (string) ($account['tk_password'] ?? '');

        if (!self::matchesPassword($stored, $password)) {
            return false;
        }

        if (self::isPasswordHash($stored)) {
            return true;
        }

        // Long legacy plaintext must remain an exact match, never a truncated
        // bcrypt upgrade. The owner can replace it through the password form.
        if (strlen($password) > 72) {
            $latest = self::find((int) $account['tk_id']);
            return $latest && hash_equals((string) $latest['tk_password'], $stored);
        }

        // Upgrade verified legacy plaintext without overwriting a newer password.
        if (self::replacePasswordHash(
            (int) $account['tk_id'],
            $stored,
            password_hash($password, PASSWORD_BCRYPT)
        )) {
            return true;
        }

        $latest = self::find((int) $account['tk_id']);
        return $latest && self::isPasswordHash($latest['tk_password'])
            && self::matchesPassword($latest['tk_password'], $password);
    }

    public static function changePassword(
        int $userId,
        #[\SensitiveParameter] string $currentPassword,
        #[\SensitiveParameter] string $newPassword
    ): bool {
        $account = self::find($userId);

        if (!$account || !self::matchesPassword($account['tk_password'], $currentPassword)) {
            return false;
        }

        return self::replacePasswordHash(
            $userId,
            $account['tk_password'],
            password_hash($newPassword, PASSWORD_BCRYPT)
        );
    }

    private static function isPasswordHash(#[\SensitiveParameter] string $stored): bool
    {
        // Hash-like values, including damaged hashes, never use plaintext fallback.
        return password_get_info($stored)['algoName'] !== 'unknown'
            || preg_match('/^\$(?:2[abxy]\$|argon2(?:i|id)\$|[156]\$)/', $stored) === 1;
    }

    private static function matchesPassword(
        #[\SensitiveParameter] string $stored,
        #[\SensitiveParameter] string $password
    ): bool {
        if ($stored === '' || str_contains($password, "\0")) {
            return false;
        }

        if (self::isPasswordHash($stored)) {
            if (strlen($password) > 72 && preg_match('/^\$2[abxy]\$/', $stored) === 1) {
                return false;
            }
            return password_verify($password, $stored);
        }

        return hash_equals($stored, $password);
    }

    private static function replacePasswordHash(
        int $userId,
        #[\SensitiveParameter] string $expectedPassword,
        #[\SensitiveParameter] string $newHash
    ): bool {
        $statement = Database::connection()->prepare(
            'UPDATE account SET tk_password = ?
             WHERE tk_id = ? AND tk_password = ?'
        );
        $statement->execute([$newHash, $userId, $expectedPassword]);
        return $statement->rowCount() === 1;
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
