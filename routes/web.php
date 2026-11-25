<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Central\InvoiceController;
use App\Http\Controllers\Central\LoginController as CentralLoginController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// The five routes of a paper that posts into the journal: list, form, record, cancel, receipt.
$paper = function (string $uri, string $controller, string $name): void {
    Route::get($uri, [$controller, 'index'])->name("$name.index");
    Route::get("$uri/create", [$controller, 'create'])->name("$name.create");
    Route::post($uri, [$controller, 'store'])->name("$name.store");
    Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->whereNumber('id');
    Route::get("$uri/{id}/receipt", [$controller, 'receipt'])->name("$name.receipt")->whereNumber('id');
};

// The six routes of a simple list-and-form screen.
$crud = function (string $uri, string $controller, string $name): void {
    Route::get($uri, [$controller, 'index'])->name("$name.index");
    Route::get("$uri/create", [$controller, 'create'])->name("$name.create");
    Route::post($uri, [$controller, 'store'])->name("$name.store");
    Route::get("$uri/{id}/edit", [$controller, 'edit'])->name("$name.edit")->whereNumber('id');
    Route::put("$uri/{id}", [$controller, 'update'])->name("$name.update")->whereNumber('id');
    Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->whereNumber('id');
};

// the language can be chosen before and after signing in, on every address
Route::post('language', [PreferenceController::class, 'locale'])->name('preferences.locale');

/*
|--------------------------------------------------------------------------
| System administrator panel (the central addresses only)
|--------------------------------------------------------------------------
*/
// what search engines may read: nothing, unless the system is set to be listed (APP_INDEXABLE)
Route::get('robots.txt', fn () => response("User-agent: *\n".(config('app.indexable') ? "Allow: /\n" : "Disallow: /\n"), 200, ['Content-Type' => 'text/plain']))->name('robots');

// the public website of the system, on the central addresses: what it is, what it costs, the request form
Route::middleware('central')->name('site.')->group(function () {
    Route::get('register', [\App\Http\Controllers\SiteController::class, 'register'])->name('register');
    Route::post('register', [\App\Http\Controllers\SiteController::class, 'store'])->name('register.store')->middleware('throttle:10,60');
    Route::get('register/{token}', [\App\Http\Controllers\SiteController::class, 'status'])->name('status')->where('token', '[a-z0-9]{32}');
    Route::get('open', [\App\Http\Controllers\SiteController::class, 'open'])->name('open');
    Route::get('register-check', [\App\Http\Controllers\SiteController::class, 'check'])->name('check')->middleware('throttle:90,1,check');
    // the Approve and Reject buttons of the email the administrator is sent (a signed address)
    Route::match(['get', 'post'], 'registration/{id}/{action}', [\App\Http\Controllers\SiteController::class, 'decide'])->name('decide')->whereNumber('id')->whereIn('action', ['approve', 'reject'])->middleware('throttle:30,10,decide');
});

Route::middleware('central')->prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [CentralLoginController::class, 'show'])->name('login');
    Route::post('login', [CentralLoginController::class, 'store'])->name('login.store');

    Route::middleware('auth:admin')->group(function () {
        $c = '\\App\\Http\\Controllers\\Central\\';
        Route::post('logout', [CentralLoginController::class, 'destroy'])->name('logout');
        Route::get('logo', [$c.'SettingsController', 'logo'])->name('logo');

        Route::get('/', [$c.'DashboardController', 'index'])->name('home');

        // clients
        Route::middleware('staff:clients.view')->group(function () use ($c) {
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/{id}', [$c.'ClientController', 'show'])->name('tenants.show')->whereNumber('id');
        });
        Route::middleware('staff:clients.manage')->group(function () use ($c) {
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::get('tenants/{id}/edit', [TenantController::class, 'edit'])->name('tenants.edit')->whereNumber('id');
            Route::put('tenants/{id}', [TenantController::class, 'update'])->name('tenants.update')->whereNumber('id');
            Route::post('tenants/{id}/status', [TenantController::class, 'status'])->name('tenants.status')->whereNumber('id');
            Route::post('tenants/{id}/login', [$c.'ClientController', 'login'])->name('tenants.login')->whereNumber('id');
            Route::put('tenants/{id}/note', [$c.'ClientController', 'note'])->name('tenants.note')->whereNumber('id');
        });
        // requests made on the website
        Route::middleware('staff:clients.view')->get('signups', [$c.'SignupController', 'index'])->name('signups.index');
        Route::middleware('staff:clients.manage')->group(function () use ($c) {
            Route::post('signups/{id}/approve', [$c.'SignupController', 'approve'])->name('signups.approve')->whereNumber('id');
            Route::post('signups/{id}/reject', [$c.'SignupController', 'reject'])->name('signups.reject')->whereNumber('id');
            Route::post('signups/delete', [$c.'SignupController', 'destroy'])->name('signups.destroy');
        });
        Route::post('tenants/{id}/enter', [$c.'ClientController', 'enter'])->name('tenants.enter')->whereNumber('id')->middleware('staff:enter');

        // money
        Route::middleware('staff:billing.view')->group(function () use ($c) {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show')->whereNumber('id');
            Route::get('payments', [$c.'PaymentController', 'index'])->name('payments.index');
            Route::get('payments/{id}/receipt', [$c.'PaymentController', 'receipt'])->name('payments.receipt')->whereNumber('id');
            // who paid and who is due, branch by branch; the renewals of every year; the resellers and their accounts
            Route::get('subscriptions', [$c.'SubscriptionController', 'index'])->name('subscriptions.index');
            Route::get('renewals', [$c.'SubscriptionController', 'renewals'])->name('renewals.index');
            Route::get('sellers', [$c.'SellerController', 'index'])->name('sellers.index');
            Route::get('sellers/{id}', [$c.'SellerController', 'show'])->name('sellers.show')->whereNumber('id');
        });
        Route::middleware('staff:billing.manage')->group(function () use ($c) {
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/{id}/status', [InvoiceController::class, 'status'])->name('invoices.status')->whereNumber('id');
            Route::put('prices', [InvoiceController::class, 'prices'])->name('prices.update');
            Route::post('payments', [$c.'PaymentController', 'store'])->name('payments.store');
            Route::delete('payments/{id}', [$c.'PaymentController', 'destroy'])->name('payments.destroy')->whereNumber('id');
            Route::post('sellers', [$c.'SellerController', 'store'])->name('sellers.store');
            Route::put('sellers/{id}', [$c.'SellerController', 'update'])->name('sellers.update')->whereNumber('id');
            Route::post('sellers/assign', [$c.'SellerController', 'assign'])->name('sellers.assign');
            Route::post('sellers/{id}/settlements', [$c.'SellerController', 'settle'])->name('sellers.settle')->whereNumber('id');
            Route::delete('sellers/{id}/settlements/{settlement}', [$c.'SellerController', 'cancel'])->name('sellers.cancel')->whereNumber('id')->whereNumber('settlement');
            Route::put('sellers/{id}/login', [$c.'SellerController', 'login'])->name('sellers.login')->whereNumber('id');
            Route::post('sellers/claims/{claim}/confirm', [$c.'SellerController', 'confirm'])->name('sellers.confirm')->whereNumber('claim');
            Route::post('sellers/claims/{claim}/reject', [$c.'SellerController', 'refuse'])->name('sellers.refuse')->whereNumber('claim');
        });
        Route::get('income', [$c.'IncomeController', 'index'])->name('income.index')->middleware('staff:reports');

        // the list of medicines of the platform: kept here, copied into every clinic
        Route::middleware('staff:drugs')->group(function () use ($c) {
            Route::get('medicines', [$c.'DrugListController', 'index'])->name('drugs.index');
            Route::post('medicines', [$c.'DrugListController', 'store'])->name('drugs.store');
            Route::put('medicines/{id}', [$c.'DrugListController', 'update'])->name('drugs.update')->whereNumber('id');
            Route::post('medicines/{id}/status', [$c.'DrugListController', 'status'])->name('drugs.status')->whereNumber('id');
            Route::post('medicines/send', [$c.'DrugListController', 'sync'])->name('drugs.sync');
        });

        // talking to the clients
        Route::middleware('staff:announcements')->group(function () use ($c) {
            Route::get('announcements', [$c.'AnnouncementController', 'index'])->name('announcements.index');
            Route::post('announcements', [$c.'AnnouncementController', 'store'])->name('announcements.store');
            Route::put('announcements/{id}', [$c.'AnnouncementController', 'update'])->name('announcements.update')->whereNumber('id');
            Route::delete('announcements/{id}', [$c.'AnnouncementController', 'destroy'])->name('announcements.destroy')->whereNumber('id');
        });
        Route::middleware('staff:tickets')->group(function () use ($c) {
            Route::get('tickets', [$c.'TicketController', 'index'])->name('tickets.index');
            Route::get('tickets/{id}', [$c.'TicketController', 'show'])->name('tickets.show')->whereNumber('id');
            Route::put('tickets/{id}', [$c.'TicketController', 'update'])->name('tickets.update')->whereNumber('id');
        });


        // the system
        Route::middleware('staff:backups')->group(function () use ($c) {
            Route::get('backups', [$c.'BackupController', 'index'])->name('backups.index');
            Route::post('backups', [$c.'BackupController', 'store'])->name('backups.store');
            Route::get('backups/{id}/download', [$c.'BackupController', 'download'])->name('backups.download')->whereNumber('id');
            Route::post('backups/{id}/restore', [$c.'BackupController', 'restore'])->name('backups.restore')->whereNumber('id');
            Route::delete('backups/{id}', [$c.'BackupController', 'destroy'])->name('backups.destroy')->whereNumber('id');
        });
        Route::middleware('staff:health')->group(function () use ($c) {
            Route::get('health', [$c.'HealthController', 'index'])->name('health.index');
            Route::post('health/migrate', [$c.'HealthController', 'migrate'])->name('health.migrate');
            Route::post('health/clear', [$c.'HealthController', 'clear'])->name('health.clear');
        });
        Route::get('logs', [$c.'LogController', 'index'])->name('logs.index')->middleware('staff:logs');
        Route::middleware('staff:staff')->group(function () use ($c) {
            Route::get('staff', [$c.'StaffController', 'index'])->name('staff.index');
            Route::post('staff', [$c.'StaffController', 'store'])->name('staff.store');
            Route::put('staff/{id}', [$c.'StaffController', 'update'])->name('staff.update')->whereNumber('id');
        });
        Route::middleware('staff:settings')->group(function () use ($c) {
            Route::get('settings', [$c.'SettingsController', 'edit'])->name('settings.edit');
            Route::put('settings', [$c.'SettingsController', 'update'])->name('settings.update');
        });
    });
});

/*
|--------------------------------------------------------------------------
| The panel of the resellers (salesmen and marketing agents)
|--------------------------------------------------------------------------
| Each sees only his own: the clinics he sold, their payments, his commission and his account.
*/
Route::middleware('central')->prefix('reseller')->name('reseller.')->group(function () {
    $r = '\\App\\Http\\Controllers\\Reseller\\';
    Route::get('login', [$r.'LoginController', 'show'])->name('login');
    Route::post('login', [$r.'LoginController', 'store'])->name('login.store');

    Route::middleware(['auth:seller', \App\Http\Middleware\ActiveSeller::class])->group(function () use ($r) {
        Route::post('logout', [$r.'LoginController', 'destroy'])->name('logout');
        Route::get('/', [$r.'PanelController', 'home'])->name('home');
        Route::get('clinics', [$r.'PanelController', 'clients'])->name('clients');
        Route::get('activation', [$r.'PanelController', 'activations'])->name('activations');
        Route::post('activation', [$r.'PanelController', 'activate'])->name('activate')->middleware('throttle:20,60,activate');
        Route::get('payments', [$r.'PanelController', 'payments'])->name('payments');
        Route::post('payments', [$r.'PanelController', 'report'])->name('report');
        Route::delete('payments/{id}', [$r.'PanelController', 'withdraw'])->name('withdraw')->whereNumber('id');
        Route::get('commissions', [$r.'PanelController', 'commissions'])->name('commissions');
        Route::get('finances', [$r.'PanelController', 'finances'])->name('finances');
        Route::match(['get', 'put'], 'account', [$r.'PanelController', 'account'])->name('account');
    });
});

/*
|--------------------------------------------------------------------------
| A clinic (every other address)
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () use ($crud, $paper) {
    // the support staff of Fida Technologies come in from the administration panel, with their reason
    Route::get('support-enter', [LoginController::class, 'support'])->name('support.enter');

    // the QR code of a printed prescription opens this page: anyone may check it, nobody signs in
    Route::get('rx/{token}', [\App\Http\Controllers\PrescriptionController::class, 'verify'])->name('rx.verify')->where('token', '[a-z0-9]{32}')->middleware('throttle:60,1,rx');

    // the logo of the clinic is also the icon of its browser tab, so the sign-in page can ask for it too
    Route::get('company-logo', [SettingsController::class, 'logo'])->name('settings.logo');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () use ($crud, $paper) {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // the service of Fida Technologies, seen from inside the clinic
        Route::get('service/licence', [\App\Http\Controllers\ServiceController::class, 'licence'])->name('service.licence');
        Route::get('service/invoices/{id}', [\App\Http\Controllers\ServiceController::class, 'invoice'])->name('service.invoice')->whereNumber('id');
        Route::get('service/support', [\App\Http\Controllers\ServiceController::class, 'tickets'])->name('service.tickets');
        Route::post('service/support', [\App\Http\Controllers\ServiceController::class, 'open'])->name('service.tickets.open');
        Route::get('service/support/{id}', [\App\Http\Controllers\ServiceController::class, 'ticket'])->name('service.ticket')->whereNumber('id');
        Route::post('service/support/{id}', [\App\Http\Controllers\ServiceController::class, 'reply'])->name('service.ticket.reply')->whereNumber('id');

        Route::post('calendar', [PreferenceController::class, 'calendar'])->name('preferences.calendar');
        Route::get('profile', [PreferenceController::class, 'profile'])->name('profile.edit');
        Route::put('profile', [PreferenceController::class, 'updateProfile'])->name('profile.update');
        Route::get('password', [PreferenceController::class, 'password'])->name('password.edit');
        Route::put('password', [PreferenceController::class, 'updatePassword'])->name('password.update');
        Route::post('refresh', [PreferenceController::class, 'refresh'])->name('system.refresh');
        Route::get('messages', [PreferenceController::class, 'notices'])->name('notices.index');
        Route::get('messages/{id}', [PreferenceController::class, 'openNotice'])->name('notices.open')->whereNumber('id');
        Route::post('messages/read', [PreferenceController::class, 'readNotices'])->name('notices.read');

        Route::get('/', DashboardController::class)->name('dashboard');

        // patients: the list and its search, registration, the page of one patient
        Route::get('patients/search', [\App\Http\Controllers\PatientController::class, 'search'])->name('patients.search');
        $crud('patients', \App\Http\Controllers\PatientController::class, 'patients');
        Route::get('patients/{id}', [\App\Http\Controllers\PatientController::class, 'show'])->name('patients.show')->whereNumber('id');
        Route::get('patients/{id}/merge', [\App\Http\Controllers\PatientController::class, 'mergeForm'])->name('patients.merge')->whereNumber('id');
        Route::post('patients/{id}/merge', [\App\Http\Controllers\PatientController::class, 'merge'])->name('patients.merge.store')->whereNumber('id');

        // appointments: the calendar of the day, booking, the buttons of a visit, walk-ins and the queue
        $a = \App\Http\Controllers\AppointmentController::class;
        Route::get('appointments', [$a, 'index'])->name('appointments.index');
        Route::get('appointments/create', [$a, 'create'])->name('appointments.create');
        Route::post('appointments', [$a, 'store'])->name('appointments.store');
        Route::get('appointments/{id}/edit', [$a, 'edit'])->name('appointments.edit')->whereNumber('id');
        Route::put('appointments/{id}', [$a, 'update'])->name('appointments.update')->whereNumber('id');
        Route::post('appointments/{id}/{action}', [$a, 'act'])->name('appointments.act')->whereNumber('id')
            ->whereIn('action', array_keys(\App\Models\Appointment::ACTIONS));
        Route::get('queue', [$a, 'queue'])->name('queue.index');
        Route::post('queue/{doctor}/next', [$a, 'callNext'])->name('queue.next')->whereNumber('doctor');

        // the set-up of the clinic: its doctors and the kinds of appointment it gives
        $crud('doctors', \App\Http\Controllers\DoctorController::class, 'doctors');
        $crud('appointment-types', \App\Http\Controllers\AppointmentTypeController::class, 'appointment-types');

        // the history of a patient: entries of every section, the way of life, vital signs, documents
        $h = \App\Http\Controllers\HistoryController::class;
        $sections = array_keys(\App\Support\History::sections());
        Route::post('patients/{patient}/history/{section}', [$h, 'store'])->name('history.store')->whereNumber('patient')->whereIn('section', $sections);
        Route::put('patients/{patient}/history/{section}/{id}', [$h, 'update'])->name('history.update')->whereNumber(['patient', 'id'])->whereIn('section', $sections);
        Route::post('patients/{patient}/history/{section}/{id}/error', [$h, 'error'])->name('history.error')->whereNumber(['patient', 'id'])
            ->whereIn('section', array_merge($sections, ['vitals', 'documents']));
        Route::post('patients/{patient}/social', [$h, 'social'])->name('history.social')->whereNumber('patient');
        Route::post('patients/{patient}/vitals', [$h, 'vitals'])->name('history.vitals')->whereNumber('patient');
        Route::post('patients/{patient}/documents', [$h, 'upload'])->name('history.upload')->whereNumber('patient');
        Route::get('patients/{patient}/documents/{id}', [$h, 'document'])->name('history.document')->whereNumber(['patient', 'id']);
        Route::get('icd10', [$h, 'icd10'])->name('icd10.search');

        // the visit with the doctor: written, diagnosed, signed and locked
        $e = \App\Http\Controllers\EncounterController::class;
        Route::post('visits', [$e, 'start'])->name('encounters.start');
        Route::get('visits/{id}', [$e, 'show'])->name('encounters.show')->whereNumber('id');
        Route::put('visits/{id}', [$e, 'update'])->name('encounters.update')->whereNumber('id');
        Route::post('visits/{id}/template', [$e, 'template'])->name('encounters.template')->whereNumber('id');
        Route::post('visits/{id}/diagnoses', [$e, 'addDiagnosis'])->name('encounters.diagnoses.store')->whereNumber('id');
        Route::delete('visits/{id}/diagnoses/{diagnosis}', [$e, 'removeDiagnosis'])->name('encounters.diagnoses.destroy')->whereNumber(['id', 'diagnosis']);
        Route::post('visits/{id}/sign', [$e, 'sign'])->name('encounters.sign')->whereNumber('id');
        Route::post('visits/{id}/addenda', [$e, 'addendum'])->name('encounters.addenda.store')->whereNumber('id');

        // prescriptions: written, checked, signed, printed with a QR code; cancelled, never changed
        $p = \App\Http\Controllers\PrescriptionController::class;
        Route::post('prescriptions', [$p, 'start'])->name('prescriptions.start');
        Route::get('prescriptions/{id}', [$p, 'show'])->name('prescriptions.show')->whereNumber('id');
        Route::put('prescriptions/{id}', [$p, 'update'])->name('prescriptions.update')->whereNumber('id');
        Route::post('prescriptions/{id}/items', [$p, 'addItem'])->name('prescriptions.items.store')->whereNumber('id');
        Route::put('prescriptions/{id}/items/{item}', [$p, 'updateItem'])->name('prescriptions.items.update')->whereNumber(['id', 'item']);
        Route::delete('prescriptions/{id}/items/{item}', [$p, 'removeItem'])->name('prescriptions.items.destroy')->whereNumber(['id', 'item']);
        Route::post('prescriptions/{id}/set', [$p, 'applySet'])->name('prescriptions.sets.apply')->whereNumber('id');
        Route::post('prescriptions/{id}/save-set', [$p, 'saveSet'])->name('prescriptions.sets.store')->whereNumber('id');
        Route::delete('prescription-sets/{set}', [$p, 'deleteSet'])->name('prescriptions.sets.destroy')->whereNumber('set');
        Route::post('prescriptions/{id}/sign', [$p, 'sign'])->name('prescriptions.sign')->whereNumber('id');
        Route::post('prescriptions/{id}/cancel', [$p, 'cancel'])->name('prescriptions.cancel')->whereNumber('id');
        Route::get('prescriptions/{id}/print', [$p, 'print'])->name('prescriptions.print')->whereNumber('id');
        Route::get('medicines/search', [$p, 'medicines'])->name('medicines.search');
        $crud('medicines', \App\Http\Controllers\MedicineController::class, 'medicines');

        // billing: invoices, money taken and given back, the cash of a day; nothing is deleted
        $i = \App\Http\Controllers\InvoiceController::class;
        Route::get('invoices', [$i, 'index'])->name('invoices.index');
        Route::post('invoices', [$i, 'start'])->name('invoices.start');
        Route::get('invoices/{id}', [$i, 'show'])->name('invoices.show')->whereNumber('id');
        Route::put('invoices/{id}', [$i, 'update'])->name('invoices.update')->whereNumber('id');
        Route::post('invoices/{id}/items', [$i, 'addItem'])->name('invoices.items.store')->whereNumber('id');
        Route::delete('invoices/{id}/items/{item}', [$i, 'removeItem'])->name('invoices.items.destroy')->whereNumber(['id', 'item']);
        Route::post('invoices/{id}/pay', [$i, 'pay'])->name('invoices.pay')->whereNumber('id');
        Route::post('invoices/{id}/refund', [$i, 'refund'])->name('invoices.refund')->whereNumber('id');
        Route::post('invoices/{id}/void', [$i, 'void'])->name('invoices.void')->whereNumber('id');
        Route::get('invoices/{id}/print', [$i, 'print'])->name('invoices.print')->whereNumber('id');
        Route::get('receipts/{payment}', [$i, 'receipt'])->name('payments.print')->whereNumber('payment');
        Route::get('cash', [$i, 'daily'])->name('billing.daily');
        $crud('price-list', \App\Http\Controllers\PriceListController::class, 'services');
        $crud('note-templates', \App\Http\Controllers\NoteTemplateController::class, 'note-templates');

        // reports, and the text messages to patients
        Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show')->where('report', '[a-z]+');
        Route::get('settings/sms', [\App\Http\Controllers\SmsController::class, 'index'])->name('sms.index');
        Route::put('settings/sms', [\App\Http\Controllers\SmsController::class, 'update'])->name('sms.update');
        Route::post('settings/sms/test', [\App\Http\Controllers\SmsController::class, 'test'])->name('sms.test')->middleware('throttle:10,1');

        // administration
        $crud('branches', BranchController::class, 'branches');
        $crud('users', UserController::class, 'users');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit')->whereNumber('id');
        Route::put('roles/{id}', [RoleController::class, 'update'])->name('roles.update')->whereNumber('id');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy')->whereNumber('id');
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('clinic-licence', [SettingsController::class, 'licence'])->name('settings.licence');

        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    });
});
