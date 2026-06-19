# Lab Analytics API Documentation

Base URL variable: `{{base_url}}`

Auth: `Authorization: Bearer {{token}}`

## Newly Added Endpoint

### GET `/api/lab/analytics`

Status: Newly Added

Purpose: Returns the complete Lab Analytics screen payload required by the React analytics page: stat cards, case type pie chart, monthly completed cases chart, clinic/doctor performance table, and detailed case list.

Controller Method: `App\Http\Controllers\Api\Lab\AnalyticsController@overview`

Service Method: `App\Services\Lab\AnalyticsService::overview`

Validation: `App\Http\Requests\Lab\AnalyticsRequest`

Query Parameters:

| Parameter | Type | Required | Notes |
| --- | --- | --- | --- |
| `startDate` | date | No | Filters cases by `created_at >= startDate`. |
| `endDate` | date | No | Filters cases by `created_at <= endDate`; must be after or equal `startDate`. |
| `clinicId` | integer or `all` | No | Matches React filter. `all` returns every clinic. |
| `dentistId` | integer or `all` | No | Matches React filter. `all` returns every doctor. |
| `caseType` | string or `all` | No | Matches React filter. `all` returns every case type. |
| `status` | string | No | One of `Pending`, `Accepted`, `In Progress`, `Completed`, `Delivered`. |
| `page` | integer | No | Detailed case list page. Default `1`. |
| `per_page` | integer | No | Detailed case list size. Default `100`, max `200`. |

Query Used:

```php
CaseModel::query()
    ->with([
        'clinic:id,name',
        'lab:id,name',
        'patient:id,user_id',
        'patient.user:id,name',
        'dentist:id,user_id',
        'dentist.user:id,name',
        'technician:id,name',
        'deliveryRep:id,name',
    ])
    ->where('lab_id', auth()->user()->lab_id)
    ->when($filters['startDate'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
    ->when($filters['endDate'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
    ->when(($filters['clinicId'] ?? 'all') !== 'all', fn ($q) => $q->where('clinic_id', (int) $filters['clinicId']))
    ->when(($filters['dentistId'] ?? 'all') !== 'all', fn ($q) => $q->where('dentist_id', (int) $filters['dentistId']))
    ->when(($filters['caseType'] ?? 'all') !== 'all', fn ($q) => $q->where('case_type', $filters['caseType']))
    ->orderBy('due_date')
    ->orderByDesc('id')
    ->get();
```

Revenue Query:

```php
LabService::query()
    ->where('lab_id', auth()->user()->lab_id)
    ->pluck('price', 'service_name');
```

Example Request:

```http
GET {{base_url}}/api/lab/analytics?startDate=2026-06-01&endDate=2026-06-30&clinicId=all&dentistId=all&caseType=all
Authorization: Bearer {{token}}
Accept: application/json
```

Example Response:

```json
{
  "status": "success",
  "success": true,
  "message": "Lab analytics fetched successfully",
  "data": {
    "filters": {
      "startDate": "2026-06-01",
      "endDate": "2026-06-30",
      "clinicId": "all",
      "dentistId": "all",
      "caseType": "all"
    },
    "stats": {
      "totalCompletedCases": 5,
      "activeCases": 1,
      "averageRevenuePerCase": 120,
      "averageTurnaroundTime": 11.2
    },
    "caseTypeBreakdown": [
      { "name": "Zirconia Crown", "value": 3, "percent": "50" },
      { "name": "E-Max Crown", "value": 1, "percent": "17" },
      { "name": "E-Max Veneer", "value": 1, "percent": "17" },
      { "name": "PFM Crown", "value": 1, "percent": "17" }
    ],
    "monthlyCompletedCases": [
      { "name": "Jun 26", "month": 6, "year": 2026, "Completed Cases": 5 }
    ],
    "performanceOverview": [
      {
        "clinicId": "1",
        "clinicName": "Bright Smiles Dental",
        "dentistId": "3",
        "dentistName": "Dr. Emily Adams",
        "totalCases": 2,
        "completedCases": 2,
        "onTimeRate": 100,
        "avgDuration": 16,
        "mostCommonCaseType": "Zirconia Crown",
        "totalRevenue": 270
      }
    ],
    "detailedCaseList": {
      "items": [
        {
          "id": 2,
          "caseNumber": "LO-M-002",
          "clinicId": 1,
          "labId": 1,
          "patientId": 4,
          "dentistId": 3,
          "status": "Delivered",
          "priority": "Normal",
          "dueDate": "2026-06-19",
          "caseType": "PFM Crown",
          "toothNumbers": [24],
          "description": null,
          "assignedTechnicianId": null,
          "assignedDeliveryId": null,
          "createdBy": 1,
          "completedAt": "2026-06-18T10:30:00.000000Z",
          "deliveredAt": "2026-06-18T15:30:00.000000Z",
          "createdAt": "2026-06-02T09:00:00.000000Z",
          "updatedAt": "2026-06-18T15:30:00.000000Z",
          "clinic": { "id": 1, "name": "Bright Smiles Dental" },
          "lab": { "id": 1, "name": "Precision Dental Labs" },
          "patient": { "id": 4, "name": "Moamen Ahmed" },
          "dentist": { "id": 3, "name": "Dr. Emily Adams" }
        }
      ],
      "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 100,
        "total": 6
      }
    },
    "totals": {
      "totalCases": 6,
      "completedCases": 5,
      "totalRevenue": 720,
      "completedRevenue": 600
    }
  }
}
```

React Field Mapping:

| React UI | Response Field |
| --- | --- |
| Total Completed Cases | `data.stats.totalCompletedCases` |
| Active Cases | `data.stats.activeCases` |
| Avg. Revenue / Case | `data.stats.averageRevenuePerCase` |
| Avg. Turnaround Time | `data.stats.averageTurnaroundTime` |
| Case Type Breakdown | `data.caseTypeBreakdown` |
| Monthly Completed Cases | `data.monthlyCompletedCases` |
| Performance Overview by Clinic/Doctor | `data.performanceOverview` |
| Detailed Case List | `data.detailedCaseList.items` |

## Existing Lab Endpoints

These routes already existed and were not modified.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/lab/dashboard/stats` | Dashboard stat cards. |
| GET | `/api/lab/dashboard/charts` | Dashboard chart datasets. |
| GET | `/api/lab/dashboard/active-cases` | Paginated active dashboard cases. |
| GET | `/api/lab/accounting/summary` | Accounting summary. |
| GET | `/api/lab/accounting/invoices` | Invoice list. |
| POST | `/api/lab/accounting/invoices` | Create invoice. |
| GET | `/api/lab/accounting/invoices/{invoice}` | Invoice details. |
| POST | `/api/lab/accounting/invoices/{invoice}` | Update invoice. |
| GET | `/api/lab/accounting/invoices/{invoice}/export` | Export invoice. |
| POST | `/api/lab/accounting/invoices/generate-monthly` | Generate monthly invoices. |
| POST | `/api/lab/accounting/invoices/{invoice}/payments` | Record invoice payment. |
| POST | `/api/lab/accounting/invoices/{invoice}/whatsapp` | Queue invoice WhatsApp message. |
| GET | `/api/lab/accounting/expenses` | Expense list. |
| POST | `/api/lab/accounting/expenses` | Create expense. |
| POST | `/api/lab/accounting/expenses/{expense}` | Update expense. |
| DELETE | `/api/lab/accounting/expenses/{expense}` | Delete expense. |
| GET | `/api/lab/accounting/expense-categories` | Expense categories. |
| POST | `/api/lab/accounting/expense-categories` | Create expense category. |
| POST | `/api/lab/accounting/expense-categories/{category}` | Update expense category. |
| DELETE | `/api/lab/accounting/expense-categories/{category}` | Delete expense category. |
| GET | `/api/lab/accounting/technician-earnings` | Technician earnings summary. |
| GET | `/api/lab/accounting/reports/top-paying-clinics` | Top paying clinics. |
| GET | `/api/lab/accounting/analytics` | Accounting-only analytics for technician/material charts. |
| GET | `/api/lab/cases` | Case list. |
| POST | `/api/lab/cases` | Create case. |
| GET | `/api/lab/cases/{id}` | Case details. |
| PATCH | `/api/lab/cases/{id}` | Update case. |
| PATCH | `/api/lab/cases/{id}/status` | Update case status. |
| POST | `/api/lab/cases/{id}/assign-technician` | Assign technician. |
| POST | `/api/lab/cases/{caseId}/assign-delivery` | Assign delivery representative. |
| GET | `/api/lab/cases/{id}/messages` | Case messages. |
| POST | `/api/lab/cases/{id}/messages` | Send case message. |
| POST | `/api/lab/cases/{id}/attachments` | Add case attachment. |
| GET | `/api/lab/cases/{id}/activity-log` | Case activity log. |
| GET | `/api/lab/clinics` | Partnered clinic list. |
| POST | `/api/lab/clinics/invite` | Invite clinic. |
| POST | `/api/lab/clinics/external` | Create external clinic. |
| GET | `/api/lab/clinics/{clinic}` | Clinic details. |
| GET | `/api/lab/clinics/{clinic}/cases` | Clinic cases. |
| DELETE | `/api/lab/clinics/{clinic}/partnership` | Delete clinic partnership. |
| GET | `/api/lab/patients` | Patient lookup. |
| GET | `/api/lab/dentists` | Dentist lookup. |
| GET | `/api/lab/technicians` | Technician lookup. |
| GET | `/api/lab/materials` | Material list. |
| POST | `/api/lab/materials` | Create material. |
| GET | `/api/lab/materials/low-stock` | Low-stock materials. |
| GET | `/api/lab/materials/expiring` | Expiring materials. |
| GET | `/api/lab/materials/{material}` | Material details. |
| PATCH | `/api/lab/materials/{material}` | Update material. |
| DELETE | `/api/lab/materials/{material}` | Delete material. |
| GET | `/api/lab/support/tickets` | Support tickets. |
| POST | `/api/lab/support/tickets` | Create support ticket. |
| GET | `/api/lab/support/tickets/{id}` | Support ticket details. |
| GET | `/api/lab/delivery-reps` | Delivery reps. |
| POST | `/api/lab/delivery-reps` | Create delivery rep. |
| GET | `/api/lab/delivery-reps/{id}` | Delivery rep details. |
| PATCH | `/api/lab/delivery-reps/{id}` | Update delivery rep. |
| DELETE | `/api/lab/delivery-reps/{id}` | Delete delivery rep. |
| GET | `/api/lab/delivery-reports` | Delivery reports. |
| GET | `/api/lab/delivery-tasks` | Delivery tasks. |
| PATCH | `/api/lab/delivery-tasks/{taskId}/location` | Update delivery task location. |
| PATCH | `/api/lab/delivery-tasks/{taskId}/status` | Update delivery task status. |
| GET | `/api/lab/equipments` | Equipment list. |
| POST | `/api/lab/equipments` | Create equipment. |
| GET | `/api/lab/equipments/{id}` | Equipment details. |
| PATCH | `/api/lab/equipments/{id}` | Update equipment. |
| DELETE | `/api/lab/equipments/{id}` | Delete equipment. |
| POST | `/api/lab/equipments/{id}/record-maintenance` | Record equipment maintenance. |
| GET | `/api/lab/select/{resource}` | Shared select options. |
| GET | `/api/lab/settings/users` | Lab settings users. |
| POST | `/api/lab/settings/users` | Create settings user. |
| PATCH | `/api/lab/settings/users/{user}` | Update settings user. |
| PATCH | `/api/lab/settings/users/{user}/status` | Update user status. |
| GET | `/api/lab/settings/services` | Lab services. |
| POST | `/api/lab/settings/services` | Create lab service. |
| PATCH | `/api/lab/settings/services/{service}` | Update lab service. |
| DELETE | `/api/lab/settings/services/{service}` | Delete lab service. |
| GET | `/api/lab/settings/profile` | Lab profile. |
| PATCH | `/api/lab/settings/profile` | Update lab profile. |
| GET | `/api/lab/settings/gallery` | Gallery images. |
| POST | `/api/lab/settings/gallery` | Upload gallery image. |
| DELETE | `/api/lab/settings/gallery/{image}` | Delete gallery image. |
| GET | `/api/lab/settings/whatsapp` | WhatsApp settings. |
| PATCH | `/api/lab/settings/whatsapp` | Update WhatsApp settings. |
| POST | `/api/lab/settings/whatsapp/test` | Test WhatsApp settings. |
| GET | `/api/lab/settings/whatsapp/logs` | WhatsApp logs. |
| GET | `/api/lab/settings/notifications` | Notification settings. |
| PATCH | `/api/lab/settings/notifications` | Update notification settings. |
| GET/POST | `/api/lab/api/whatsapp/webhook` | Public WhatsApp webhook. |

## Completion Notes

No existing endpoint response shape was changed. The existing `/api/lab/accounting/analytics` endpoint remains dedicated to technician earnings and material commission analytics. The new `/api/lab/analytics` endpoint covers the React Lab Analytics screen without duplicating the accounting endpoint.
