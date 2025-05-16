cd /mnt/efs/scripts/cron/

for archive in $@ ; do
    if [ $(ps -ef | grep -v grep | grep  "./$archive" | wc -l) -lt 10 ]; then
        ./processoIzaPhp.sh 0.0 $archive &
        ./processoIzaPhp.sh 3.0 $archive &
        ./processoIzaPhp.sh 6.0 $archive &
        ./processoIzaPhp.sh 9.0 $archive &
        ./processoIzaPhp.sh 12.0 $archive &
        ./processoIzaPhp.sh 15.0 $archive &
        ./processoIzaPhp.sh 18.0 $archive &
        ./processoIzaPhp.sh 21.0 $archive &
        ./processoIzaPhp.sh 24.0 $archive &
        ./processoIzaPhp.sh 27.0 $archive &
        ./processoIzaPhp.sh 30.0 $archive &
        ./processoIzaPhp.sh 33.0 $archive &
        ./processoIzaPhp.sh 36.0 $archive &
        ./processoIzaPhp.sh 39.0 $archive &
        ./processoIzaPhp.sh 42.0 $archive &
        ./processoIzaPhp.sh 45.0 $archive &
        ./processoIzaPhp.sh 48.0 $archive &
        ./processoIzaPhp.sh 51.0 $archive &
        ./processoIzaPhp.sh 54.0 $archive &
        ./processoIzaPhp.sh 57.0 $archive &
    fi
done