<?php

namespace App;

enum ActivityType: string
{
    case DATABASE_STUDIO_ACTIVITY = 'database_studio_activity';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case PROFILE_UPDATE = 'profile_update';
    case PASSWORD_UPDATE = 'password_update';
    case PASSWORD_RESET = 'password_reset';
    case PASSWORD_RESET_REQUEST = 'password_reset_request';
    case DATABASE_CREATE = 'database_create';
    case DATABASE_DELETE = 'database_delete';
    case DATABASE_UPDATE = 'database_update';
    case DATABASE_TOKEN_CREATE = 'database_token_create';
    case DATABASE_TOKEN_DELETE = 'database_token_delete';
    case DATABASE_TOKEN_UPDATE = 'database_token_update';
    case GROUP_DATABASE_CREATE = 'group_database_create';
    case GROUP_DATABASE_DELETE = 'group_database_delete';
    case GROUP_DATABASE_UPDATE = 'group_database_update';
    case GROUP_DATABASE_TOKEN_CREATE = 'group_database_token_create';
    case GROUP_DATABASE_TOKEN_DELETE = 'group_database_token_delete';
    case GROUP_DATABASE_TOKEN_UPDATE = 'group_database_token_update';
    case TEAM_CREATE = 'team_create';
    case TEAM_DELETE = 'team_delete';
    case TEAM_UPDATE = 'team_update';
    case TEAM_MEMBER_CREATE = 'team_member_create';
    case TEAM_MEMBER_DELETE = 'team_member_delete';
    case TEAM_MEMBER_UPDATE = 'team_member_update';
    case USER_CREATE = 'user_create';
    case USER_DELETE = 'user_delete';
    case USER_UPDATE = 'user_update';
    case USER_RESTORE = 'user_restore';
    case USER_DEACTIVATE = 'user_deactivate';
    case USER_REACTIVATE = 'user_reactivate';
    case USER_FORCE_DELETE = 'user_force_delete';
    case ROLE_CREATE = 'role_create';
    case ROLE_DELETE = 'role_delete';
    case ROLE_UPDATE = 'role_update';
}
