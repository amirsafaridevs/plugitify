import { build, context } from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const outfile = resolve(here, '../src/muPlugin/view/assets/js/agent.bundle.js');

const watch = process.argv.includes('--watch');
const dev = watch || process.argv.includes('--dev');

/** @type {import('esbuild').BuildOptions} */
const options = {
  entryPoints: [resolve(here, 'src/main.ts')],
  outfile,
  bundle: true,
  format: 'esm',
  platform: 'browser',
  target: 'es2022',
  minify: !dev,
  sourcemap: dev ? 'inline' : false,
  logLevel: 'info',
  // The SDK reads a handful of process.env values for defaults it never needs
  // in the browser — the config comes from plugitify.php instead. Without this
  // shim those reads are a ReferenceError at load time.
  banner: {
    js: 'globalThis.process = globalThis.process || { env: {}, versions: {}, platform: "browser" };',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(dev ? 'development' : 'production'),
  },
};

if (watch) {
  const ctx = await context(options);
  await ctx.watch();
  console.log('watching…');
} else {
  await build(options);
}
