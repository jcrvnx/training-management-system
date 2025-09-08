<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
*/

$route['default_controller'] = 'dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

/*
| -------------------------------------------------------------------------
| Custom Routes
| -------------------------------------------------------------------------
*/

// Dashboard
$route['dashboard'] = 'dashboard/index';
$route['^$'] = 'dashboard/index';

// Authentication routes
$route['login'] = 'auth/login';
$route['register'] = 'auth/register';
$route['logout'] = 'auth/logout';
$route['forgot-password'] = 'auth/forgot_password';
$route['unauthorized'] = 'auth/unauthorized';

// Training routes
$route['trainings'] = 'training/index';
$route['trainings/create'] = 'training/create';
$route['trainings/view/(:num)'] = 'training/view/$1';
$route['trainings/edit/(:num)'] = 'training/edit/$1';
$route['trainings/delete/(:num)'] = 'training/delete/$1';
$route['trainings/enroll/(:num)'] = 'training/enroll/$1';

// User management routes
$route['users'] = 'users/index';
$route['users/create'] = 'users/create';
$route['users/view/(:num)'] = 'users/view/$1';
$route['users/edit/(:num)'] = 'users/edit/$1';
$route['users/delete/(:num)'] = 'users/delete/$1';

// Enrollment routes
$route['enrollments'] = 'enrollment/index';
$route['enrollments/create'] = 'enrollment/create';
$route['enrollments/view/(:num)'] = 'enrollment/view/$1';
$route['enrollments/edit/(:num)'] = 'enrollment/edit/$1';
$route['enrollments/delete/(:num)'] = 'enrollment/delete/$1';

// Quiz routes
$route['quizzes'] = 'quiz/index';
$route['quizzes/create'] = 'quiz/create';
$route['quizzes/view/(:num)'] = 'quiz/view/$1';
$route['quizzes/edit/(:num)'] = 'quiz/edit/$1';
$route['quizzes/delete/(:num)'] = 'quiz/delete/$1';
$route['quizzes/attempt/(:num)'] = 'quiz/attempt/$1';
$route['quizzes/submit/(:num)'] = 'quiz/submit/$1';
$route['quizzes/results/(:num)'] = 'quiz/results/$1';
$route['quizzes/manage/(:num)'] = 'quiz/manage/$1';

// Certificate routes
$route['certificates'] = 'certificate/index';
$route['certificates/view/(:num)'] = 'certificate/view/$1';
$route['certificates/download/(:num)'] = 'certificate/download/$1';
$route['certificates/my-certificates'] = 'certificate/my_certificates';

// API routes for AJAX
$route['api/notifications/mark-read/(:num)'] = 'api/notification/mark_read/$1';

