module.exports = {
  apps: [{
    name: 'beachrex-mcp',
    script: 'dist/index.js',
    cwd: '/opt/beachrex/api_dev/mcp-server',
    env: {
      BEACHREX_API_URL: 'https://dev-api.beachrex.com',
      BEACHREX_API_KEY: '',
      MCP_HTTP_PORT: '3100',
    },
    max_restarts: 10,
    max_memory_restart: '200M',
  }],
};
