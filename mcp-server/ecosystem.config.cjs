/**
 * PM2 config for the Beachrex MCP server, one process per environment.
 *
 *   MCP_ENV=dev  pm2 startOrReload ecosystem.config.cjs --update-env   -> beachrex-mcp-dev  :3100
 *   MCP_ENV=uat  pm2 startOrReload ecosystem.config.cjs --update-env   -> beachrex-mcp-uat  :3101
 *   MCP_ENV=prod pm2 startOrReload ecosystem.config.cjs --update-env   -> beachrex-mcp-prod :3102
 *
 * MCP_ENV defaults to dev. cwd is this directory, so each env runs its own
 * checkout (/opt/beachrex/api_<env>/mcp-server).
 * BEACHREX_API_KEY, if ever needed, is read from the shell env of the pm2 command, never from git.
 */
const ENVIRONMENTS = {
  dev: {
    apiUrl: 'https://dev-api.beachrex.com',
    frontendUrl: 'https://dev.beachrex.com',
    port: 3100,
  },
  uat: {
    apiUrl: 'https://uat-api.beachrex.com',
    frontendUrl: 'https://uat.beachrex.com',
    port: 3101,
  },
  prod: {
    apiUrl: 'https://api.beachrex.com',
    frontendUrl: 'https://beachrex.com',
    port: 3102,
  },
};

const envName = (process.env.MCP_ENV || 'dev').trim().toLowerCase();
const env = ENVIRONMENTS[envName];
if (!env) {
  throw new Error(`Unknown MCP_ENV "${envName}". Use one of: ${Object.keys(ENVIRONMENTS).join(', ')}`);
}

module.exports = {
  apps: [{
    name: `beachrex-mcp-${envName}`,
    script: 'dist/index.js',
    cwd: __dirname,
    env: {
      NODE_ENV: 'production',
      MCP_ENV: envName,
      BEACHREX_API_URL: env.apiUrl,
      BEACHREX_FRONTEND_URL: env.frontendUrl,
      BEACHREX_API_KEY: process.env.BEACHREX_API_KEY || '',
      MCP_HTTP_PORT: String(env.port),
    },
    max_restarts: 10,
    max_memory_restart: '200M',
  }],
};
