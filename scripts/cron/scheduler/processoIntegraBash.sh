cd /mnt/efs/scripts/cron/

for archive in $@ ; do
    if [ $(ps -ef | grep -v grep | grep  "./$archive" | wc -l) -lt 10 ]; then
        ./processoIntegraPhp.sh 0.0 $archive &
        ./processoIntegraPhp.sh 3.0 $archive &
        ./processoIntegraPhp.sh 6.0 $archive &
        ./processoIntegraPhp.sh 9.0 $archive &
        ./processoIntegraPhp.sh 12.0 $archive &
        ./processoIntegraPhp.sh 15.0 $archive &
        ./processoIntegraPhp.sh 18.0 $archive &
        ./processoIntegraPhp.sh 21.0 $archive &
        ./processoIntegraPhp.sh 24.0 $archive &
        ./processoIntegraPhp.sh 27.0 $archive &
        ./processoIntegraPhp.sh 30.0 $archive &
        ./processoIntegraPhp.sh 33.0 $archive &
        ./processoIntegraPhp.sh 36.0 $archive &
        ./processoIntegraPhp.sh 39.0 $archive &
        ./processoIntegraPhp.sh 42.0 $archive &
        ./processoIntegraPhp.sh 45.0 $archive &
        ./processoIntegraPhp.sh 48.0 $archive &
        ./processoIntegraPhp.sh 51.0 $archive &
        ./processoIntegraPhp.sh 54.0 $archive &
        ./processoIntegraPhp.sh 57.0 $archive &
    fi
done