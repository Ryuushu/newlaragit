@servers(['web' => 'gitserver'])

@setup
$app_dir = '/var/www/app';
$repo_dir = $app_dir.'/../newlara';
$releases_dir = $app_dir.'/releases';
$release = date('YmdHis');
$new_release_dir = $releases_dir.'/'.$release;
$branch = $branch ?? 'master';
@endsetup


@story('deploy')
    pull_repository
    prepare_release
    run_composer
    run_laravel
    update_symlink
    cleanup
@endstory


# ========================
# 1. PULL TERBARU
# ========================
@task('pull_repository')
    git config --global --add safe.directory /var/www/newlara
    echo "Pull latest code"

    cd {{ $repo_dir }}

    git fetch origin
    git checkout {{ $branch }}
    git reset --hard origin/{{ $branch }}
@endtask


# ========================
# 2. COPY KE RELEASE BARU
# ========================
@task('prepare_release')
    echo "Preparing release {{ $release }}"

    mkdir -p {{ $releases_dir }}
    cp -r {{ $repo_dir }} {{ $new_release_dir }}
@endtask


# ========================
# 3. COMPOSER
# ========================
@task('run_composer')
    echo "Install dependencies"

    cd {{ $new_release_dir }}
    composer install --no-dev --optimize-autoloader
@endtask


# ========================
# 4. LARAVEL SETUP
# ========================
@task('run_laravel')
    echo "Laravel optimization"

    cd {{ $new_release_dir }}

    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
@endtask


# ========================
# 5. ZERO DOWNTIME SWITCH
# ========================
@task('update_symlink')
    echo "Link storage & env"

    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo "Switching current release (ZERO DOWNTIME)"
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo "Restart services"
    cd {{ $new_release_dir }}
    php artisan queue:restart || true
@endtask


# ========================
# 6. CLEANUP
# ========================
@task('cleanup')
    echo "Cleaning old releases"

    cd {{ $releases_dir }}
    ls -dt */ | tail -n +6 | xargs rm -rf
@endtask