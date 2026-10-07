# Command line

The admin screen caps each request at 1,000 donations. For anything larger, and for anything
scripted, use WP-CLI. On a wp-env site, prefix each command with `npx @wordpress/env run cli`.

## Donations at scale

```
wp give-data donations <count> [--campaigns=<n>] [--donors=<n>] [--mode=test|live] [--status=<status>|random]
```

```bash
wp give-data donations 1000000 --campaigns=50 --donors=100000
wp give-data donations 5000 --mode=live --status=complete
```

- Every donation goes through the Donation model, so rows look exactly like production writes.
  About fifteen minutes per 100,000 on a quiet machine.
- The count is additive: run it twice, get twice as many.
- Campaigns are created with a default form each when the site has fewer than `--campaigns`.
  Donations are spread across them and dated over the last three years.
- Donations are created in test mode unless you pass `--mode=live`. `--status` is one status for
  every donation, or `random` for a realistic mix (70% complete).
- Email and the per-donation campaign cache job are suspended while generating. Everything that
  writes data still runs. Donor totals and campaign caches are rebuilt once at the end.
- Progress is logged every ten seconds.

```
wp give-data reset [--yes]
```

Deletes every donation and donor this command generated, and nothing else. Generated rows are
tagged, so real data on the same site is untouched.

## Pages for every block and shortcode

```
wp give-data pages (--campaign=<id> | --form=<id>) [--layout=individual|type|single] [--only=<names>]
```

```bash
wp give-data pages --campaign=12
wp give-data pages --form=34 --layout=single
wp give-data pages --campaign=12 --only=givewp/campaign-grid,give_form
```

- Pass `--campaign` or `--form`. A standalone form skips the campaign blocks and shortcodes.
- `--layout` is `individual` (a page per block or shortcode, default), `type` (one page for
  blocks, one for shortcodes) or `single` (everything on one page).
- `--only` takes block names or shortcode tags; an unknown name lists the available ones.

## Benchmarking, snapshots and restore

`wp give-data bench`, `wp give-data snapshot` and `wp give-data restore` are covered in
[benchmarking.md](benchmarking.md), with a walkthrough.
