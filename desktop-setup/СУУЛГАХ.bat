@echo off
chcp 65001 >nul
title Manage Dornogovi - суулгац
cd /d "%~dp0"

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
