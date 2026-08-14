<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Auth')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::get('/', 'showLoginForm')->name('login');
            Route::post('/', 'login')->name('login');
            Route::get('logout', 'logout')->middleware('admin')->withoutMiddleware('admin.guest')->name('logout');
        });

        // Admin Password Reset
        Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function () {
            Route::get('reset', 'showLinkRequestForm')->name('reset');
            Route::post('reset', 'sendResetCodeEmail');
            Route::get('code-verify', 'codeVerify')->name('code.verify');
            Route::post('verify-code', 'verifyCode')->name('verify.code');
        });

        Route::controller('ResetPasswordController')->group(function () {
            Route::get('password/reset/{token}', 'showResetForm')->name('password.reset.form');
            Route::post('password/reset/change', 'reset')->name('password.change');
        });
    });
});

Route::middleware('admin')->group(function () {
    Route::controller('AdminController')->group(function () {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('chart/deposit-withdraw', 'depositAndWithdrawReport')->name('chart.deposit.withdraw');
        Route::get('chart/transaction', 'transactionReport')->name('chart.transaction');
        Route::get('profile', 'profile')->name('profile');
        Route::post('profile', 'profileUpdate')->name('profile.update');
        Route::get('password', 'password')->name('password');
        Route::post('password', 'passwordUpdate')->name('password.update');

        //Notification
        Route::get('notifications', 'notifications')->name('notifications');
        Route::get('notification/read/{id}', 'notificationRead')->name('notification.read');
        Route::get('notifications/read-all', 'readAllNotification')->name('notifications.read.all');
        Route::post('notifications/delete-all', 'deleteAllNotification')->name('notifications.delete.all');
        Route::post('notifications/delete-single/{id}', 'deleteSingleNotification')->name('notifications.delete.single');

        //Report Bugs
        Route::get('request-report', 'requestReport')->name('request.report');
        Route::post('request-report', 'reportSubmit');

        Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
    });

    // Users Manager
    Route::controller('ManageUsersController')->name('users.')->prefix('users')->group(function () {
        Route::get('/', 'allUsers')->name('all');
        Route::get('active', 'activeUsers')->name('active');
        Route::get('banned', 'bannedUsers')->name('banned');
        Route::get('email-verified', 'emailVerifiedUsers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedUsers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedUsers')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedUsers')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingUsers')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedUsers')->name('mobile.verified');
        Route::get('with-balance', 'usersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::post('update/{id}', 'update')->name('update');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::get('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');
    });

    Route::controller('CategoryController')->name('category.')->prefix('category')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('all', 'allCategories')->name('all');
        Route::get('sub/{parentId}', 'subCategories')->name('sub');
        Route::post('store/{id?}', 'store')->name('store');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('import', 'import')->name('import');
        Route::post('import-ajax', 'importAjax')->name('import.ajax');
        Route::post('import-process', 'importProcess')->name('import.process');
        Route::get('import-progress', 'importProgress')->name('import.progress');
        Route::post('import-reset', 'importReset')->name('import.reset');
        Route::get('example-csv', 'exampleCsv')->name('example.csv');
    });
    Route::controller('ManageExamController')->name('exams.')->prefix('exams')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('result/declare', 'result')->name('result.declare');
        Route::post('publish/result/{id}', 'publishResult')->name('publish.result');
        Route::get('questions/{id}', 'questions')->name('questions');
        Route::post('questions/store/{exam_id}/{id?}', 'questionStore')->name('question.store');
        Route::post('questions/status/{id}', 'questionStatus')->name('question.status');
        Route::post('questions/result/{id}', 'questionResult')->name('question.result');
        Route::post('option/store/{id?}', 'optionStore')->name('option.store');
        Route::post('option/status/{id}', 'optionStatus')->name('option.status');
        Route::get('add/{id?}', 'add')->name('add');
        Route::post('store/{id?}', 'store')->name('store');
        Route::post('status/{id}', 'status')->name('status');

        Route::post('generate/question/{exam_id}', 'questionGenerate')->name('question.generate');
    });

    Route::controller('QuizController')->name('quiz.')->prefix('quiz')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('create/{id?}', 'create')->name('create');
        Route::post('store/{id?}', 'store')->name('store');
        Route::get('show/{id}', 'show')->name('show');
        Route::get('sub-categories', 'getSubCategories')->name('subcategories');
        Route::post('status/{id}', 'changeQuizStatus')->name('status');
        Route::post('delete/{id}', 'delete')->name('delete');
        Route::post('restore/{id}', 'restore')->name('restore');
        Route::get('preview/{id}', 'preview')->name('preview');
    });

    Route::controller('QuestionBankController')->name('question-bank.')->prefix('question-bank')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::post('store/{id?}', 'store')->name('store');
        Route::post('delete/{id}', 'delete')->name('delete');
        Route::post('status/{id}', 'status')->name('status');

        Route::post('quiz/add/{quizId}', 'addToQuiz')->name('quiz.add');
        Route::post('quiz/remove/{quizId}/{questionId}', 'removeFromQuiz')->name('quiz.remove');
        Route::post('quiz/reorder/{quizId}', 'reorderQuestions')->name('quiz.reorder');
        Route::post('quiz/marks/{quizId}/{questionId}', 'updateQuestionMarks')->name('quiz.marks');
        Route::get('quiz/available/{quizId}', 'getAvailableQuestions')->name('quiz.available');

        Route::post('option/store/{questionId}/{optionId?}', 'storeOption')->name('option.store');
        Route::post('option/delete/{questionId}/{optionId}', 'deleteOption')->name('option.delete');
    });

    // SubscriberController and its two views already existed but had no routes,
    // leaving the admin Subscribers page unreachable. Registered here so the
    // newsletter sign-ups collected by the public site can be managed.
    Route::controller('SubscriberController')->name('subscriber.')->prefix('subscriber')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('send-email', 'sendEmailForm')->name('send.email');
        Route::post('send-email', 'sendEmail')->name('send.email.submit');
        Route::post('remove/{id}', 'remove')->name('remove');
    });

    Route::controller('QuizImportController')->name('quiz-import.')->prefix('quiz-import')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('history', 'history')->name('history');
        Route::get('template', 'template')->name('template');
        Route::post('generate-prompt', 'generatePrompt')->name('generate.prompt');
        Route::post('upload', 'upload')->name('upload');

        Route::post('process/{id}', 'process')->name('process');
        Route::get('status/{id}', 'status')->name('status');

        Route::get('preview/{id}', 'preview')->name('preview');
        Route::post('row/{id}/{rowId}', 'updateRow')->name('row.update');
        Route::post('row/remove/{id}/{rowId}', 'removeRow')->name('row.remove');

        Route::post('approve/{id}', 'approve')->name('approve');
        Route::post('cancel/{id}', 'cancel')->name('cancel');
        Route::post('delete/{id}', 'destroy')->name('delete');
        Route::get('error-report/{id}', 'errorReport')->name('error.report');
    });

    Route::controller('AiQuestionGeneratorController')->name('ai-generator.')->prefix('ai-generator')->group(function () {
        Route::get('generate', 'create')->name('create');
        Route::post('generate', 'generate')->name('generate');
        Route::get('sub-categories', 'subCategories')->name('subcategories');

        Route::get('preview/{id}', 'preview')->name('preview');
        Route::post('bulk/{id}', 'bulkAction')->name('bulk');
        Route::post('regenerate/{id}/{questionId}', 'regenerate')->name('regenerate');
        Route::post('question/{id}/{questionId}', 'updateQuestion')->name('question.update');
        Route::post('question/delete/{id}/{questionId}', 'deleteQuestion')->name('question.delete');
        Route::post('add-to-quiz/{id}', 'addToQuiz')->name('add.to.quiz');

        Route::get('questions', 'generatedQuestions')->name('questions');
        Route::get('history', 'history')->name('history');
        Route::get('raw/{id}', 'rawResponse')->name('raw');
        Route::post('cancel/{id}', 'cancel')->name('cancel');
        Route::post('delete/{id}', 'destroy')->name('delete');
    });

    Route::controller('AiGenerationSettingController')->name('ai-settings.')->prefix('ai-settings')->group(function () {
        Route::get('', 'index')->name('index');
        Route::post('update', 'update')->name('update');
        Route::post('clear-key', 'clearKey')->name('clear.key');
        Route::post('test', 'testConnection')->name('test');
    });

    Route::controller('QuestionImportController')->name('question-import.')->prefix('question-import')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('history', 'history')->name('history');
        Route::get('template', 'template')->name('template');
        Route::post('generate-prompt', 'generatePrompt')->name('generate.prompt');
        Route::post('upload', 'upload')->name('upload');

        Route::post('process/{id}', 'process')->name('process');
        Route::get('status/{id}', 'status')->name('status');

        Route::get('preview/{id}', 'preview')->name('preview');
        Route::post('row/{id}/{rowId}', 'updateRow')->name('row.update');
        Route::post('row/remove/{id}/{rowId}', 'removeRow')->name('row.remove');

        Route::post('approve/{id}', 'approve')->name('approve');
        Route::post('cancel/{id}', 'cancel')->name('cancel');
        Route::post('delete/{id}', 'destroy')->name('delete');
        Route::get('error-report/{id}', 'errorReport')->name('error.report');
    });

    Route::controller('PlanController')->name('plan.')->prefix('plan')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('add/{id?}', 'add')->name('add');
        Route::post('store/{id?}', 'store')->name('store');
        Route::post('status/{id}', 'status')->name('status');
    });

    // Deposit Gateway
    Route::name('gateway.')->prefix('gateway')->group(function () {
        // Automatic Gateway
        Route::controller('AutomaticGatewayController')->prefix('automatic')->name('automatic.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{code}', 'update')->name('update');
            Route::post('remove/{id}', 'remove')->name('remove');
            Route::post('status/{id}', 'status')->name('status');
        });

        // Manual Methods
        Route::controller('ManualGatewayController')->prefix('manual')->name('manual.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new', 'create')->name('create');
            Route::post('new', 'store')->name('store');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });

    // DEPOSIT SYSTEM
    Route::controller('DepositController')->prefix('deposit')->name('deposit.')->group(function () {
        Route::get('all/{user_id?}', 'deposit')->name('list');
        Route::get('pending/{user_id?}', 'pending')->name('pending');
        Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
        Route::get('approved/{user_id?}', 'approved')->name('approved');
        Route::get('successful/{user_id?}', 'successful')->name('successful');
        Route::get('initiated/{user_id?}', 'initiated')->name('initiated');
        Route::get('details/{id}', 'details')->name('details');
        Route::post('reject', 'reject')->name('reject');
        Route::post('approve/{id}', 'approve')->name('approve');

    });

    // Report
    Route::controller('ReportController')->prefix('report')->name('report.')->group(function () {
        Route::get('subscriptions/{user_id?}', 'subscriptions')->name('subscriptions');
        Route::get('exams/{user_id?}', 'exams')->name('exams');
        Route::get('exams/view/{id?}', 'examView')->name('exam.view');
        Route::get('login/history', 'loginHistory')->name('login.history');
        Route::get('login/ip-history/{ip}', 'loginIpHistory')->name('login.ip.history');
        Route::get('notification/history', 'notificationHistory')->name('notification.history');
        Route::get('email/detail/{id}', 'emailDetails')->name('email.details');
    });

    // Admin Support
    Route::controller('SupportTicketController')->prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', 'tickets')->name('index');
        Route::get('pending', 'pendingTicket')->name('pending');
        Route::get('closed', 'closedTicket')->name('closed');
        Route::get('answered', 'answeredTicket')->name('answered');
        Route::get('view/{id}', 'ticketReply')->name('view');
        Route::post('reply/{id}', 'replyTicket')->name('reply');
        Route::post('close/{id}', 'closeTicket')->name('close');
        Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
        Route::post('delete/{id}', 'ticketDelete')->name('delete');
    });

    // Language Manager
    Route::controller('LanguageController')->prefix('language')->name('language.')->group(function () {
        Route::get('/', 'langManage')->name('manage');
        Route::post('/', 'langStore')->name('manage.store');
        Route::post('delete/{id}', 'langDelete')->name('manage.delete');
        Route::post('update/{id}', 'langUpdate')->name('manage.update');
        Route::get('edit/{id}', 'langEdit')->name('key');
        Route::post('import', 'langImport')->name('import.lang');
        Route::post('store/key/{id}', 'storeLanguageJson')->name('store.key');
        Route::post('delete/key/{id}', 'deleteLanguageJson')->name('delete.key');
        Route::post('update/key/{id}', 'updateLanguageJson')->name('update.key');
        Route::get('get-keys', 'getKeys')->name('get.key');
    });

    Route::controller('ManageCertificateController')->name('certificate.')->prefix('certificate')->group(function () {
        Route::get('template', 'certificateTemplate')->name('template');
        Route::post('template/update', 'certificateTemplateUpdate')->name('template.update');
        Route::get('preview/{id?}', 'certificatePreview')->name('preview');
    });

    Route::controller('GeneralSettingController')->group(function () {

        Route::get('system-setting', 'systemSetting')->name('setting.system');

        // General Setting
        Route::get('general-setting', 'general')->name('setting.general');
        Route::post('general-setting', 'generalUpdate');

        Route::get('setting/social/credentials', 'socialiteCredentials')->name('setting.socialite.credentials');
        Route::post('setting/social/credentials/update/{key}', 'updateSocialiteCredential')->name('setting.socialite.credentials.update');
        Route::post('setting/social/credentials/status/{key}', 'updateSocialiteCredentialStatus')->name('setting.socialite.credentials.status.update');

        //configuration
        Route::get('setting/system-configuration', 'systemConfiguration')->name('setting.system.configuration');
        Route::post('setting/system-configuration', 'systemConfigurationSubmit');

        // Logo-Icon
        Route::get('setting/logo-icon', 'logoIcon')->name('setting.logo.icon');
        Route::post('setting/logo-icon', 'logoIconUpdate')->name('setting.logo.icon');

        //Custom CSS
        Route::get('custom-css', 'customCss')->name('setting.custom.css');
        Route::post('custom-css', 'customCssSubmit');

        Route::get('sitemap', 'sitemap')->name('setting.sitemap');
        Route::post('sitemap', 'sitemapSubmit');

        Route::get('robot', 'robot')->name('setting.robot');
        Route::post('robot', 'robotSubmit');

        //Cookie
        Route::get('cookie', 'cookie')->name('setting.cookie');
        Route::post('cookie', 'cookieSubmit');

        //maintenance_mode
        Route::get('maintenance-mode', 'maintenanceMode')->name('maintenance.mode');
        Route::post('maintenance-mode', 'maintenanceModeSubmit');

        //In app purchase
        Route::get('in-app-purchase', 'inAppPurchase')->name('setting.app.purchase');
        Route::post('in-app-purchase', 'inAppPurchaseConfigure');
        Route::get('in-app-purchase/file/download', 'inAppPurchaseFileDownload')->name('setting.app.purchase.file.download');

    });

    Route::controller('CronConfigurationController')->name('cron.')->prefix('cron')->group(function () {
        Route::get('index', 'cronJobs')->name('index');
        Route::post('store', 'cronJobStore')->name('store');
        Route::post('update', 'cronJobUpdate')->name('update');
        Route::post('delete/{id}', 'cronJobDelete')->name('delete');
        Route::get('schedule', 'schedule')->name('schedule');
        Route::post('schedule/store', 'scheduleStore')->name('schedule.store');
        Route::post('schedule/status/{id}', 'scheduleStatus')->name('schedule.status');
        Route::get('schedule/pause/{id}', 'schedulePause')->name('schedule.pause');
        Route::get('schedule/logs/{id}', 'scheduleLogs')->name('schedule.logs');
        Route::post('schedule/log/resolved/{id}', 'scheduleLogResolved')->name('schedule.log.resolved');
        Route::post('schedule/log/flush/{id}', 'logFlush')->name('log.flush');
    });

    //KYC setting
    Route::controller('KycController')->group(function () {
        Route::get('kyc-setting', 'setting')->name('kyc.setting');
        Route::post('kyc-setting', 'settingUpdate');
    });

    //Notification Setting
    Route::name('setting.notification.')->controller('NotificationController')->prefix('notification')->group(function () {
        //Template Setting
        Route::get('global/email', 'globalEmail')->name('global.email');
        Route::post('global/email/update', 'globalEmailUpdate')->name('global.email.update');

        Route::get('global/sms', 'globalSms')->name('global.sms');
        Route::post('global/sms/update', 'globalSmsUpdate')->name('global.sms.update');

        Route::get('global/push', 'globalPush')->name('global.push');
        Route::post('global/push/update', 'globalPushUpdate')->name('global.push.update');

        Route::get('templates', 'templates')->name('templates');
        Route::get('template/edit/{type}/{id}', 'templateEdit')->name('template.edit');
        Route::post('template/update/{type}/{id}', 'templateUpdate')->name('template.update');

        //Email Setting
        Route::get('email/setting', 'emailSetting')->name('email');
        Route::post('email/setting', 'emailSettingUpdate');
        Route::post('email/test', 'emailTest')->name('email.test');

        //SMS Setting
        Route::get('sms/setting', 'smsSetting')->name('sms');
        Route::post('sms/setting', 'smsSettingUpdate');
        Route::post('sms/test', 'smsTest')->name('sms.test');

        Route::get('notification/push/setting', 'pushSetting')->name('push');
        Route::post('notification/push/setting', 'pushSettingUpdate');
        Route::post('notification/push/setting/upload', 'pushSettingUpload')->name('push.upload');
        Route::get('notification/push/setting/download', 'pushSettingDownload')->name('push.download');
    });

    // Plugin
    Route::controller('ExtensionController')->prefix('extensions')->name('extensions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
    });

    //System Information
    Route::controller('SystemController')->name('system.')->prefix('system')->group(function () {
        Route::get('info', 'systemInfo')->name('info');
        Route::get('server-info', 'systemServerInfo')->name('server.info');
        Route::get('optimize', 'optimize')->name('optimize');
        Route::get('optimize-clear', 'optimizeClear')->name('optimize.clear');
        Route::get('system-update', 'systemUpdate')->name('update');
        Route::post('system-update', 'systemUpdateProcess')->name('update.process');
        Route::get('system-update/log', 'systemUpdateLog')->name('update.log');
    });

    // SEO
    Route::get('seo', 'FrontendController@seoEdit')->name('seo');

    // Frontend
    Route::name('frontend.')->prefix('frontend')->group(function () {

        Route::controller('FrontendController')->group(function () {
            Route::get('index', 'index')->name('index');
            Route::get('templates', 'templates')->name('templates');
            Route::post('templates', 'templatesActive')->name('templates.active');
            Route::get('frontend-sections/{key?}', 'frontendSections')->name('sections');
            Route::post('frontend-content/{key}', 'frontendContent')->name('sections.content');
            Route::get('frontend-element/{key}/{id?}', 'frontendElement')->name('sections.element');
            Route::get('frontend-slug-check/{key}/{id?}', 'frontendElementSlugCheck')->name('sections.element.slug.check');
            Route::get('frontend-element-seo/{key}/{id}', 'frontendSeo')->name('sections.element.seo');
            Route::post('frontend-element-seo/{key}/{id}', 'frontendSeoUpdate');
            Route::post('update-seo', 'updateSeoContent')->name('seo.update');
            Route::post('remove/{id}', 'remove')->name('remove');
        });

        // Page Builder
        Route::controller('PageBuilderController')->group(function () {
            Route::get('manage-pages', 'managePages')->name('manage.pages');
            Route::get('manage-pages/check-slug/{id?}', 'checkSlug')->name('manage.pages.check.slug');
            Route::post('manage-pages', 'managePagesSave')->name('manage.pages.save');
            Route::post('manage-pages/update', 'managePagesUpdate')->name('manage.pages.update');
            Route::post('manage-pages/delete/{id}', 'managePagesDelete')->name('manage.pages.delete');
            Route::get('manage-section/{id}', 'manageSection')->name('manage.section');
            Route::post('manage-section/{id}', 'manageSectionUpdate')->name('manage.section.update');

            Route::get('manage-seo/{id}', 'manageSeo')->name('manage.pages.seo');
            Route::post('manage-seo/{id}', 'manageSeoStore');
        });

    });

    // Gamification System
    Route::name('xp.')->prefix('gamification')->group(function () {
        // XP Dashboard
        Route::controller('XpDashboardController')->group(function () {
            Route::get('dashboard', 'index')->name('dashboard');
        });

        // XP Rules
        Route::controller('XpRulesController')->name('rules.')->prefix('rules')->group(function () {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('status/{id}', 'status')->name('status');
            Route::post('delete/{id}', 'destroy')->name('delete');
            Route::post('reorder', 'reorder')->name('reorder');
        });

        // User XP
        Route::controller('UserXpController')->name('users.')->prefix('users')->group(function () {
            Route::get('', 'index')->name('index');
            Route::get('show/{id}', 'show')->name('show');
            Route::post('add-xp/{id}', 'addXp')->name('add.xp');
            Route::post('deduct-xp/{id}', 'deductXp')->name('deduct.xp');
            Route::post('reset-xp/{id}', 'resetXp')->name('reset.xp');
        });

        // XP Transactions
        Route::controller('XpTransactionController')->name('transactions.')->prefix('transactions')->group(function () {
            Route::get('', 'index')->name('index');
            Route::get('show/{id}', 'show')->name('show');
        });

        // Levels
        Route::controller('LevelController')->name('levels.')->prefix('levels')->group(function () {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
            Route::post('delete/{id}', 'destroy')->name('delete');
        });

        // Badges
        Route::controller('BadgeController')->name('badges.')->prefix('badges')->group(function () {
            Route::get('', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
            Route::post('delete/{id}', 'destroy')->name('delete');
        });

        // Settings
        Route::controller('GamificationSettingsController')->name('settings.')->prefix('settings')->group(function () {
            Route::get('', 'index')->name('settings');
            Route::post('update', 'update')->name('update');
        });
    });
});
