import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, FileText, RefreshCw, ShoppingCart, Package, FileEdit } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { centralCashApiService, type CentralCashTransaction } from '@/services/centralCashApiService';
import { pdfRgService, type PdfRgPedido } from '@/services/pdfRgService';
import { editarPdfService, type EditarPdfPedido } from '@/services/pdfPersonalizadoService';
import { formatDate } from '@/utils/historicoUtils';

interface UnifiedOrder {
  id: string;
  type: 'pdf_rg' | 'pdf_personalizado' | 'module_purchase';
  description: string;
  amount: number;
  status: string;
  created_at: string;
  meta?: Record<string, any>;
}

const statusLabels: Record<string, string> = {
  realizado: 'Realizado',
  pagamento_confirmado: 'Pgto Confirmado',
  em_confeccao: 'Em Confecção',
  entregue: 'Entregue',
};

const HistoricoCadastrosApi = () => {
  const navigate = useNavigate();
  const [orders, setOrders] = useState<UnifiedOrder[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    try {
      const [cashResult, rgResult, persResult] = await Promise.allSettled([
        centralCashApiService.getRecentTransactions(100),
        pdfRgService.listar({ limit: 100 }),
        editarPdfService.listar({ limit: 100 }),
      ]);

      const unified: UnifiedOrder[] = [];

      // Module purchases from central cash
      if (cashResult.status === 'fulfilled' && cashResult.value.success && cashResult.value.data) {
        const modulePurchases = cashResult.value.data.filter(
          (t: CentralCashTransaction) =>
            t.transaction_type === 'compra_modulo' ||
            t.description?.toLowerCase().includes('compra de')
        );
        modulePurchases.forEach((tx: CentralCashTransaction) => {
          let parsedMeta: any = null;
          try {
            if (tx.metadata) parsedMeta = typeof tx.metadata === 'string' ? JSON.parse(tx.metadata) : tx.metadata;
          } catch {}
          unified.push({
            id: `cash_${tx.id}`,
            type: 'module_purchase',
            description: tx.description || 'Compra de módulo',
            amount: tx.amount,
            status: 'completed',
            created_at: tx.created_at,
            meta: { payment_method: tx.payment_method, module_name: parsedMeta?.module_name },
          });
        });
      }

      // PDF RG orders
      if (rgResult.status === 'fulfilled' && rgResult.value.success && rgResult.value.data) {
        rgResult.value.data.data.forEach((p: PdfRgPedido) => {
          unified.push({
            id: `rg_${p.id}`,
            type: 'pdf_rg',
            description: `PDF RG - ${p.nome || p.cpf}`,
            amount: p.preco_pago,
            status: p.status,
            created_at: p.created_at,
            meta: { cpf: p.cpf, nome: p.nome },
          });
        });
      }

      // PDF Personalizado orders
      if (persResult.status === 'fulfilled' && persResult.value.success && persResult.value.data) {
        persResult.value.data.data.forEach((p: EditarPdfPedido) => {
          unified.push({
            id: `pers_${p.id}`,
            type: 'pdf_personalizado',
            description: `PDF Personalizado - ${p.nome_solicitante}`,
            amount: p.preco_pago,
            status: p.status,
            created_at: p.created_at,
            meta: { descricao: p.descricao_alteracoes },
          });
        });
      }

      // Sort by date desc
      unified.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
      setOrders(unified);
    } catch (error) {
      console.error('Erro ao carregar cadastros:', error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const formatCurrency = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  const getTypeBadge = (type: UnifiedOrder['type']) => {
    switch (type) {
      case 'pdf_rg':
        return <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4 bg-blue-500/10 text-blue-600 border-blue-500/30">PDF RG</Badge>;
      case 'pdf_personalizado':
        return <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4 bg-violet-500/10 text-violet-600 border-violet-500/30">PDF Personalizado</Badge>;
      default:
        return <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4">Módulo</Badge>;
    }
  };

  const getBorderColor = (type: UnifiedOrder['type']) => {
    switch (type) {
      case 'pdf_rg': return 'border-l-blue-500';
      case 'pdf_personalizado': return 'border-l-violet-500';
      default: return 'border-l-emerald-500';
    }
  };

  return (
    <div className="space-y-3 sm:space-y-6 relative z-10 px-1 sm:px-0">
      <Card>
        <CardHeader className="p-3 sm:p-6">
          <div className="flex items-center justify-between gap-2">
            <CardTitle className="flex items-center gap-2 text-base sm:text-lg">
              <FileText className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
              <span className="truncate">Histórico · Cadastros na API</span>
            </CardTitle>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="h-8 sm:h-9">
                <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
              </Button>
              <Button variant="outline" size="icon" onClick={() => navigate('/dashboard/historico')} className="rounded-full h-9 w-9" aria-label="Voltar" title="Voltar">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader className="p-3 sm:p-6 pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm sm:text-base flex items-center gap-2">
              <ShoppingCart className="h-4 w-4 text-primary" />
              Pedidos e Compras
            </CardTitle>
            <Badge variant="secondary" className="text-xs">
              {orders.length} registros
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="p-3 sm:p-6 pt-0">
          {isLoading ? (
            <div className="text-center py-8">
              <RefreshCw className="h-8 w-8 animate-spin text-primary mx-auto mb-4" />
              <p className="text-muted-foreground text-sm">Carregando registros...</p>
            </div>
          ) : orders.length > 0 ? (
            <div className="space-y-2">
              {orders.map((order) => (
                <div
                  key={order.id}
                  className={`border rounded-lg p-3 sm:p-4 bg-card border-l-4 ${getBorderColor(order.type)} space-y-2`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0 space-y-1">
                      <p className="text-sm font-medium">{order.description}</p>
                      <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-xs text-muted-foreground">
                          {formatDate(order.created_at)}
                        </span>
                        {getTypeBadge(order.type)}
                        {order.status !== 'completed' && (
                          <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4">
                            {statusLabels[order.status] || order.status}
                          </Badge>
                        )}
                        {order.meta?.payment_method && (
                          <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4 uppercase">
                            {order.meta.payment_method}
                          </Badge>
                        )}
                      </div>
                    </div>
                    <Badge
                      variant="secondary"
                      className="text-xs font-semibold px-2 bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300 flex-shrink-0"
                    >
                      {formatCurrency(order.amount)}
                    </Badge>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <ShoppingCart className="h-10 w-10 mx-auto mb-3 opacity-50" />
              <p className="text-sm">Nenhum pedido registrado ainda</p>
              <p className="text-xs mt-1">Pedidos de PDF RG, PDF Personalizado e compras de módulos aparecerão aqui</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default HistoricoCadastrosApi;
