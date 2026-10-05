<?php

final class ProductVariantModel
{
    // =========================
    // MÀU SẢN PHẨM
    // =========================

    public static function colors(): array
    {
        return Database::query(
            'SELECT * FROM productcolor'
        );
    }

    public static function colorsByProduct(int $productId): array
    {
        return Database::query(
            'SELECT * FROM productcolor
             WHERE id_sp = ?',
            $productId
        );
    }

    public static function findColor(int $id)
    {
        return Database::one(
            'SELECT * FROM productcolor
             WHERE pc_id = ?',
            $id
        );
    }

    public static function createColor(
        string $name,
        int $productId
    ): void {
        Database::execute(
            'INSERT INTO productcolor(pc_name, id_sp)
             VALUES (?, ?)',
            $name,
            $productId
        );
    }

    public static function updateColor(
        int $id,
        string $name
    ): void {
        Database::execute(
            'UPDATE productcolor
             SET pc_name = ?
             WHERE pc_id = ?',
            $name,
            $id
        );
    }

    public static function deleteColor(int $id): void
    {
        Database::execute(
            'DELETE FROM productcolor
             WHERE pc_id = ?',
            $id
        );
    }

    // =========================
    // BỘ NHỚ SẢN PHẨM
    // =========================

    public static function memories(): array
    {
        return Database::query(
            'SELECT * FROM productmemory'
        );
    }

    public static function memoriesByProduct(int $productId): array
    {
        return Database::query(
            'SELECT * FROM productmemory
             WHERE id_sp = ?',
            $productId
        );
    }

    public static function findMemory(int $id)
    {
        return Database::one(
            'SELECT * FROM productmemory
             WHERE pm_id = ?',
            $id
        );
    }

    public static function createMemory(
        string $name,
        int $productId
    ): void {
        Database::execute(
            'INSERT INTO productmemory(pm_name, id_sp)
             VALUES (?, ?)',
            $name,
            $productId
        );
    }

    public static function updateMemory(
        int $id,
        string $name
    ): void {
        Database::execute(
            'UPDATE productmemory
             SET pm_name = ?
             WHERE pm_id = ?',
            $name,
            $id
        );
    }

    public static function deleteMemory(int $id): void
    {
        Database::execute(
            'DELETE FROM productmemory
             WHERE pm_id = ?',
            $id
        );
    }
}