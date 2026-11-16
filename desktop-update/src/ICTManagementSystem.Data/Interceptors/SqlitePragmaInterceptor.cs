using System.Data.Common;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace ICTManagementSystem.Data.Interceptors;

public class SqlitePragmaInterceptor : DbConnectionInterceptor
{
    private const string PragmaCommands = 
        "PRAGMA journal_mode=WAL; " +
        "PRAGMA foreign_keys=ON; " +
        "PRAGMA synchronous=FULL; " +
        "PRAGMA busy_timeout=5000;";

    public override void ConnectionOpened(DbConnection connection, ConnectionEndEventData eventData)
    {
        using var cmd = connection.CreateCommand();
        cmd.CommandText = PragmaCommands;
        cmd.ExecuteNonQuery();
        base.ConnectionOpened(connection, eventData);
    }

    public override async Task ConnectionOpenedAsync(DbConnection connection, ConnectionEndEventData eventData, CancellationToken cancellationToken = default)
    {
        using var cmd = connection.CreateCommand();
        cmd.CommandText = PragmaCommands;
        await cmd.ExecuteNonQueryAsync(cancellationToken);
        await base.ConnectionOpenedAsync(connection, eventData, cancellationToken);
    }
}

