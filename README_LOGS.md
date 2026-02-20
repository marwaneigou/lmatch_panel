# Transaction Logging System Documentation

This document describes the structure and usage of the new `transaction_logs` table, which records key operations across the platform.

## Database Schema (`transaction_logs`)

- **id**: Primary Key
- **reseller_id**: The ID of the user/reseller performing the action.
- **target_id**: The ID of the affected entity (e.g., ActiveCode ID, User ID).
- **target_type**: The type of the affected entity (string).
- **action**: The action performed (string).
- **amount**: The cost or value associated with the transaction (nullable).
- **old_balance**: The reseller's balance before the transaction (nullable).
- **new_balance**: The reseller's balance after the transaction (nullable).
- **details**: Human-readable description of the log.
- **ip**: IP address of the user performing the action.
- **created_at**, **updated_at**: Timestamps.

## Log Types and Actions

### 1. Active Codes (`active_code`)
Recorded in `ActiveCodeController`.

| Action      | Description |
| ----------- | ----------- |
| `create`    | Creation of a new Active Code. Records cost and balance changes. |
| `update`    | Modification of an Active Code (e.g., changing package/notes). |
| `enable`    | Enabling of a disabled Active Code. |
| `disable`   | Disabling of an Active Code. |
| `delete`    | Deletion of an Active Code. |
| `reset_mac` | Resetting the MAC address associated with the code. |
| `renew`     | Renewing the duration of an Active Code. Records cost and balance changes. |

### 2. Users (`user`)
Recorded in `UtilisateurController`.

| Action      | Description |
| ----------- | ----------- |
| `store`     | Creation of a new User (e.g., M3U user). Records cost and balance changes. |
| `update`    | Modification of a User account. |
| `disable`   | Disabling a User account. |
| `delete`    | Deletion of a User account. |
| `reset_mac` | Resetting the MAC address of a User. |
| `renew`     | Renewing a User's subscription. Records cost and balance changes. |

### 3. Mag Devices (`mag_device`)
Recorded in `MagDeviceController`.

| Action      | Description |
| ----------- | ----------- |
| `create`    | Activation/Creation of a MAG device. Records cost and balance changes. |
| `update`    | Modification of MAG device details. |
| `disable`   | Disabling a MAG device. |
| `delete`    | Deletion of a MAG device. |
| `reset_mac` | Resetting the linked MAC address. |
| `renew`     | Renewing a MAG subscription. Records cost and balance changes. |

### 4. Mass Codes (`mass_code` / `multi_code`)
Recorded in `MultiCodeController`.

| Target Type | Action      | Description |
| ----------- | ----------- | ----------- |
| `mass_code` | `create`    | Bulk creation of codes. Records **total** cost and balance change. `target_id` is 0. |
| `multi_code`| `update`    | Modification of a single code from the mass batch. |
| `multi_code`| `disable`   | Disabling a single code. |
| `multi_code`| `delete`    | Deleting a single code. |
| `multi_code`| `reset_mac` | Resetting MAC for a single code. |
| `multi_code`| `renew`     | Renewing a single code. |

### 5. Resellers (`reseller`)
Recorded in `ResilerController`.

| Action           | Description |
| ---------------- | ----------- |
| `create`         | Creation of a new sub-reseller. |
| `delete`         | Deletion of a sub-reseller. |
| `add_credit`     | Admin adding credits to a reseller. Records separate logs for Main, Test, App, and Gift balances. |
| `recover_credit` | Reseller recovering credits from a sub-reseller. Records recovery for Main, Test, App, and Gift balances. |

## Reseller Recharge Fix
- The `add_credit` action in `ResilerController` now **adds** the new amount to the existing balance instead of replacing it.
- Transaction logs are generated for these recharges showing `old_balance` and `new_balance`.
