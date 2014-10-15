set base_dir=%~dp0  
%base_dir:~0,2% 
pushd %base_dir%
mklink /D lib ..\..\..\..\..\tizi_lib\library\views\static\lib
popd
pause
