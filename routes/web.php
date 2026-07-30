<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'pages.dashboard')->name('dashboard');

    Route::view('/habitos/hoy', 'pages.coming-soon', ['title' => 'Hábitos de hoy'])->name('habits.today');
    Route::view('/habitos', 'pages.coming-soon', ['title' => 'Todos los hábitos'])->name('habits.index');
    Route::view('/calendario', 'pages.coming-soon', ['title' => 'Calendario anual'])->name('calendar.index');
    Route::view('/estadisticas', 'pages.coming-soon', ['title' => 'Estadísticas'])->name('stats.index');
    Route::view('/objetivos', 'pages.coming-soon', ['title' => 'Objetivos'])->name('goals.index');
    Route::view('/reflexion', 'pages.coming-soon', ['title' => 'Reflexión diaria'])->name('reflections.index');
    Route::view('/historial', 'pages.coming-soon', ['title' => 'Historial'])->name('history.index');
    Route::view('/perfil', 'pages.coming-soon', ['title' => 'Perfil y configuración'])->name('profile.edit');
});
