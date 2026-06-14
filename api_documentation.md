# CV Sorting System - API Documentation

This document describes the core API endpoints for the CV Sorting System, including task management and RPA configuration.

---

## 1. Job Task Data API
Returns a list of jobs with their task numbers and statuses.

**Endpoint:** `GET /api/get_job_task_data.php`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `status` | string | No | Filter by display order ID (e.g., `4` for pending). `0` for all. |
| `task_no` | string | No | Filter by task number (e.g., `TASK1`). `0` for all. |

### CURL Example
```bash
curl --location 'http://localhost/CV_Sorting/api/get_job_task_data.php?status=4&task_no=0'
```

---

## 2. Update Task Status API
Updates the status of a specific job by its `jd_id`.

**Endpoint:** `GET /api/update_task_status.php`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `jd_id` | string | Yes | The Job Description ID (e.g., `JDMOU0PE`). |
| `status` | int | Yes | The new status display order ID (e.g., `4` for pending). |

### CURL Example
```bash
curl --location 'http://localhost/CV_Sorting/api/update_task_status.php?jd_id=JDMOU0PE&status=4'
```

---

## 3. RPA Configuration API
Manages the robot process automation settings stored in the `rpa_config` table.

**Endpoint:** `GET/POST /api/rpa_config_api.php`

### Actions

#### A. List Configurations
Returns all key-value pairs from the configuration table.

**Parameters:** `action=list`

**CURL Example:**
```bash
curl --location 'http://localhost/CV_Sorting/api/rpa_config_api.php?action=list' \
--header 'Cookie: PHPSESSID=your_session_id'
```

#### B. Update Multiple / Single Key
Updates one or more configuration keys.

**Parameters:** `action=update_multiple`

**Usage:** Pass key-value pairs either as query parameters or in the JSON body.

**CURL Example (Query Params):**
```bash
curl --location 'http://localhost/CV_Sorting/api/rpa_config_api.php?action=update_multiple&LIVEDATA=ON&ID=joy' \
--header 'Cookie: PHPSESSID=your_session_id'
```

**CURL Example (JSON Body):**
```bash
curl --location 'http://localhost/CV_Sorting/api/rpa_config_api.php?action=update_multiple' \
--header 'Content-Type: application/json' \
--header 'Cookie: PHPSESSID=your_session_id' \
--data '{
    "LIVEDATA": "OFF",
    "LOOPCOUNT": "2"
}'
```

---

## 4. n8n Webhook Trigger API
Triggers an external n8n workflow for CV processing.

**Endpoint:** `GET/POST /api/n8n_api.php`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `jd_id` | string | Yes | The Job Description ID. |
| `job_title` | string | Yes | The title of the job. |

### CURL Example (with API Key)
```bash
curl --location 'http://localhost/CV_Sorting/api/n8n_api.php?jd_id=JDMOU0PE&job_title=AI%20Engineer' \
--header 'X-API-KEY: mgi_rpa_secret_2024'
```

---

## 5. Server Configuration API
Retrieves the current server count and audit metadata.

**Endpoint:** `GET /api/get_server_config.php`

### Response
```json
{
    "status": "success",
    "data": {
        "server_count": 5,
        "last_updated_by": "System",
        "updated_at": "2024-04-18 10:58:17"
    }
}
```

---

## 6. Update Server Configuration API
Updates the server pool count and logs the user identity.

**Endpoint:** `POST /api/update_server_config.php`

### Parameters (JSON Body)
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `server_count` | int | Yes | New server pool size (1-9). |

### Success Response
```json
{
    "status": "success",
    "message": "Server configuration updated successfully.",
    "data": {
        "server_count": 5,
        "updated_by": "Joy Ballav",
        "updated_id": "EMP123"
    }
}
```

---

---

## 7. Move JD File API
Moves Job Description files from the source directory to the processed directory. The `filename` parameter is **mandatory** to prevent accidental bulk moves.

**Endpoint:** `GET /api/move_jd_file.php`

### Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `filename` | string | **Yes** | Use a specific filename (e.g., `JD_AI.pdf`) or use `-` to move **all** files in the source directory. |
| `newfilename` | string | No | Rename the file during movement. Only works if a specific `filename` is provided. |

### CURL Example (Single File)
```bash
curl --location 'http://10.201.26.53:8080/api/move_jd_file.php?filename=JD_AI_ENG.pdf'
```

### CURL Example (Bulk Move - Move All)
```bash
curl --location 'http://10.201.26.53:8080/api/move_jd_file.php?filename=-'
```

---

## Authentication Methods

### Method 1: API Key (Recommended for RPA)
Use the `X-API-KEY` header to access the API without needing a session.

**Default API Key:** `mgi_rpa_secret_2024`

**CURL Example:**
```bash
curl --location 'http://localhost/CV_Sorting/api/rpa_config_api.php?action=list' \
--header 'X-API-KEY: mgi_rpa_secret_2024'
```

---

### Method 2: Session Cookie (Web Users)
Include a valid `PHPSESSID` cookie. Ensure you include **exactly one** valid ID.

**CURL Example:**
```bash
curl --location 'http://localhost/CV_Sorting/api/rpa_config_api.php?action=list' \
--header 'Cookie: PHPSESSID=your_valid_session_id'
```

> [!WARNING]
> **Duplicate Session IDs**: Do not include multiple `PHPSESSID` values. Doing so will trigger a security warning.

> [!NOTE]
> If you are redirected to `login.php`, it indicates your authentication (Session or API Key) failed.
