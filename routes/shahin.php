<?php

use App\Http\Controllers\Api\AvaialableProgramController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ClubDashboardController;
use App\Http\Controllers\Api\ClubProfileController;
use App\Http\Controllers\Api\ClubTeamController;
use App\Http\Controllers\Api\CoachAuthController;
use App\Http\Controllers\Api\CoachDashboardController;
use App\Http\Controllers\Api\CoacherProgramController;
use App\Http\Controllers\Api\CoachPositionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MatchBidController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ParentChildController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\PlayerAuthController;
use App\Http\Controllers\Api\PlayerCoachRecruitmentController;
use App\Http\Controllers\Api\PlayerDashboardController;
use App\Http\Controllers\Api\PlayerPaymentController;
use App\Http\Controllers\Api\PlayerPositionController;
use App\Http\Controllers\Api\ProgramBookingController;
use App\Http\Controllers\Api\RecruitementController;
use App\Http\Controllers\Api\SearchExploreController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\SubscriptionPlanApiController;
use Illuminate\Support\Facades\Route;











Route::controller(ParentController::class)->group(function () {
    Route::post('parent/register', 'register');
});

Route::controller(SubscriptionPlanApiController::class)->group(function () {
    Route::get('subscription/plans', 'index');
    Route::get('subscription/plan/{plan_id}', 'show');
    Route::get('weebhook/stripe', 'handleStripeWebhook');
});


Route::middleware('auth:api')->group(function () {
    Route::controller(ParentController::class)->group(function () {
        Route::post('parent/aggrement', 'aggrement');
        Route::get('parent/profile', 'getProfile');
        Route::post('parent/profile/update', 'UpdateParentProfile');
    });

    Route::controller(ParentChildController::class)->group(function () {
        Route::post('parent/child/add', 'addChild');
        Route::post('parent/child/update/{child_id}', 'updateAthleteProfile');
        Route::get('parent/child/list', 'listChildren');
        Route::get('parent/child/remove/{child_id}', 'removeChild');
        Route::get('profile/view/{child_id}', 'getProfileData');

        Route::get('profile/player/data/{child_id}', 'getPlayerData');

        Route::get('profile/athelete/galeery/delete/{media_id}', 'deleteGalleryMedia');
        Route::get('profile/athelete/reel/delete/{media_id}', 'deleteReelMedia');
        Route::get('profile/athelete/achievement/delete/{achievement_id}', 'deleteAchievement');

        Route::post('profile/player/strength/endorse', 'endorseStrength');

        Route::post('/child/send/invitation', 'sendInvitation');
        Route::get('parent/child/block/{child_id}', 'blockChild');
        Route::get('parent/child/unblock/{child_id}', 'unblockChild');
    });

    Route::controller(PlayerAuthController::class)->group(function () {
        Route::post('player/profile/add', 'PlayerRegister');
        Route::post('player/profile/update', 'updatePlayerProfile');
    });


    ///////////////////////////coach////////////////////////////

    Route::controller(CoachAuthController::class)->group(function () {

        Route::post('coach/profile/add/update', 'AddUpdateCoachProfile');
        Route::get('coach/profile', 'getCoachProfile');
        Route::get('coach/list', 'coachList');
        Route::post('coach/media/delete/{media_id}', 'deleteCoachMedia');

        Route::get('coach/profile/data/edit', 'editdata');
    });

    Route::controller(CoacherProgramController::class)->group(function () {
        Route::post('coach/program/add', 'store');
        Route::post('coach/program/update/{program_id}', 'update');
        Route::get('coach/program/list', 'list');

        Route::get('coach/program/view/{program_id}', 'show');
        Route::post('coach/program/buy/{program_id}', 'buy');
        Route::post('coach/program/review/{program_id}', 'submitReview');
        Route::delete('coach/program/delete/{program_id}', 'delete');
    });

    Route::controller(AvaialableProgramController::class)->group(function () {
        Route::get('program/available/list', 'listAvailablePrograms');
        Route::get('program/available/view/{program_id}', 'viewProgramDetails');
    });

    //////////////////club////////////////////////////
    Route::controller(ClubProfileController::class)->group(function () {
        Route::post('club/profile/add/update', 'AddUpdateClubProfile');
        Route::get('club/profile', 'getClubProfile');
        Route::post('club/media/delete/{media_id}', 'deleteClubMedia');

        Route::get('club/profile/data/edit', 'editdata');
        Route::get('organization/types', 'getOrganizationTypes');
    });

    Route::controller(SubscriptionPlanApiController::class)->group(function () {
        Route::post('club/subscription/purchase', 'purchase');
        Route::get('club/subscription/list', 'listSubscriptions');
        Route::get('/club/subscription/update', 'changePlan');
    });

    Route::controller(ClubTeamController::class)->group(function () {
        Route::post('club/team/add', 'store');
        Route::post('club/team/update/{team_id}', 'update');
        Route::get('club/team/list', 'list');
        Route::get('club/team/view/{team_id}', 'show');
        Route::delete('club/team/delete/{team_id}', 'delete');

        Route::get('club/team/players/list/{team_id}', 'listTeamPlayersandCoaches');
    });


    Route::controller(PlayerPositionController::class)->group(function () {
        Route::get('player/positions', 'index');
    });

    Route::controller(CoachPositionController::class)->group(function () {
        Route::get('coach/positions', 'index');
    });

    Route::controller(RecruitementController::class)->group(function () {
        Route::post('club/recruitments/store', 'store');
        Route::get('club/recruitments/list', 'list');
        Route::post('club/recruitments/update/{recruitment_id}', 'update');
        Route::get('club/recruitments/view/{recruitment_id}', 'show');
        Route::post('club/recruitments/delete/{recruitment_id}', 'delete');
    });

    Route::controller(MatchController::class)->group(function () {
        Route::post('club/match/store/update', 'updateCreate');
        Route::get('club/match/list', 'list');
        Route::get('club/match/view/{match_id}', 'show');
        Route::post('club/match/delete/{match_id}', 'delete');
    });

    Route::controller(PlayerCoachRecruitmentController::class)->group(function () {
        Route::post('recruitment/apply', 'apply');
        Route::get('recruitment/apply/list', 'listApplications');
        Route::post('recruitment/apply/update/{application_id}', 'updateApplicationStatus');
    });

    Route::controller(SearchExploreController::class)->group(function () {
        Route::post('search/explore/list', 'list');
        Route::post('search/explore/view/player', 'viewexploreprofile');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::get('parent/dashboard', 'parentDashboard');
    });

    Route::controller(PlayerDashboardController::class)->group(function () {
        Route::get('player/dashboard', 'playerDashboard');
    });

    Route::controller(CoachDashboardController::class)->group(function () {
        Route::get('coach/dashboard', 'coachDashboard');
        Route::get('coach/earnings/export', 'exportEarnings');
    });

    Route::controller(ClubDashboardController::class)->group(function () {
        Route::get('club/dashboard', 'clubDashboard');
        Route::post('club/settings/update', 'updateSettings');
    });

    Route::controller(ProgramBookingController::class)->group(function () {
        Route::post('program/booking', 'bookProgram');
        Route::get('program/booking/list/athlete', 'AthleteParentlistBookings');
        Route::get('program/booking/view/{program_id}', 'viewBookingDetails');
        Route::post('program/booking/cancel/{booking_id}', 'updateStatus');

        ///////////////////////////coach.///////////////////////
        Route::get('coach/program/bookings', 'coachProgramBookings');
        Route::get('coach/earnings/view', 'coachEarningsView');
    });

    Route::controller(StripeController::class)->group(function () {
        Route::post('stripe/account/set', 'createStripeAccount');
        Route::post('stripe/data/get', 'getStripeData');
    });

    Route::controller(PlayerPaymentController::class)->group(function () {
        Route::post('player/payment/list', 'playerPaymentList');
        Route::get('player/payment/download/{booking_id?}', 'downloadInvoice');
        Route::post('player/payment/dowload/{booking_id}', 'downloadInvoice');
    });



    Route::controller(ChatController::class)->group(function () {
        Route::post('/chat/send', 'sendMessage');
        Route::get('/chat/mark/read/{conversation_id}', 'markAsRead');
        Route::get('/chat/get/{conversation_id}', 'getConversation');
        Route::get('chat/list/data', 'getchatlist');
        Route::get('/chat/delete/{chat_id}', 'chatdelete');
        Route::get('/chat/image/delete/{image_id}', 'chatImageDelete');
    });








    Route::controller(MatchBidController::class)->group(function () {
        Route::post('match/bid', 'placeBid');
        Route::get('match/bids/list', 'listBidsForMatch');
        Route::post('match/bid/update/{bid_id}', 'updateBidStatus');
    });
});


Route::controller(ProgramBookingController::class)->group(function () {
    Route::post('program/booking/hook', 'handle');
});
